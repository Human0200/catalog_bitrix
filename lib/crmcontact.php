<?php
/**
 *    Different info from store
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
	Bitrix\Main\Type,
	Bitrix\Main\Entity,
	Bitrix\Main\Localization\Loc,
	Bitrix\Main\SiteTable,
	Bitrix\Sale;

Loc::loadMessages(__FILE__);

class CrmContact
{
    const MODULE_ID = 'sproduction.integration';

	public static function getById($id) {
		$result = false;
		if ($id) {
			$result = Rest::execute('crm.contact.get', [
				'id' => $id
			]);
		}
		return $result;
	}

	/**
	 * Contacts search
	 */
	public static function findContact(array $order_data, $deal_info, $profile) {
		$contact = false;
		if (Rest::checkConnection()) {
			$cont_fields = self::getDealContactDataByProfile($order_data, [], $profile);
			$cont_s_field = $profile['contact']['contact_search_fields'];
			if ($cont_s_field) {
				if ($cont_fields[$cont_s_field]) {
					$filter = [
						$cont_s_field => $cont_fields[$cont_s_field],
					];
					$request = [
						'list' => [
							'method' => 'crm.contact.list',
							'params' => [
								'filter' => $filter,
							],
							'start' => -1
						],
						'get'  => [
							'method' => 'crm.contact.get',
							'params' => [
								'id' => '$result[list][0][ID]',
							]
						]
					];
					$res = Rest::batch($request);
					if ($res['get']) {
						$contact = $res['get'];
					}
				}
			} else {
				if ($cont_fields['PHONE'] && $cont_fields['PHONE'][0]['VALUE']) {
					$search_phone = $cont_fields['PHONE'][0]['VALUE'];
				}
				if ($cont_fields['EMAIL'] && $cont_fields['EMAIL'][0]['VALUE']) {
					$search_email = $cont_fields['EMAIL'][0]['VALUE'];
				}
				// Find by phone
				if ($search_phone) {
					$phones = self::getPhonesFormats($search_phone);
					foreach ($phones as $phone) {
						$filter = [
							'PHONE' => $phone,
						];
						$request = [
							'list' => [
								'method' => 'crm.contact.list',
								'params' => [
									'filter' => $filter,
								],
								'start' => -1
							],
							'get'  => [
								'method' => 'crm.contact.get',
								'params' => [
									'id' => '$result[list][0][ID]',
								]
							]
						];
						$res = Rest::batch($request);
						if ($res['get']['ID']) {
							$contact = $res['get'];
						}
					}
				}
				// Find by email
				if ( ! $contact && $search_email) {
					$filter = [
						'EMAIL' => $search_email,
					];
					$request = [
						'list' => [
							'method' => 'crm.contact.list',
							'params' => [
								'filter' => $filter,
							],
							'start' => -1
						],
						'get'  => [
							'method' => 'crm.contact.get',
							'params' => [
								'id' => '$result[list][0][ID]',
							]
						]
					];
					$res = Rest::batch($request);
					if ($res['get']['ID']) {
						$contact = $res['get'];
					}
				}
			}
			\SProdIntegration::Log('(findContact) finded contact "' . $contact['ID'] . '" by ' . print_r($filter, true));
		}

		return $contact;
	}

	/**
	 * Sync the deal contact data
	 */
	public static function syncOrderToDealContact(array $order_data, $deal_info, $profile) {
		$result = false;
		$contact_id = false;
		if (Rest::checkConnection()) {
			$sync_new_type = (int) $profile['contact']['sync_new_type'];
			$deal = $deal_info['deal'];
			$contact = $deal_info['contact'];
			\SProdIntegration::Log('(syncOrderToDealContact) current contact '.print_r($contact, true));
			// Find contact
			if (self::syncOrderToDealContactNeedFindNew($order_data, $deal_info, $profile)) {
				$contact = self::findContact($order_data, $deal_info, $profile);
			}
			// Get contacts data
			$cont_fields = self::getDealContactDataByProfile($order_data, $contact, $profile);
			$cont_fields = Utilities::convEncForDeal($cont_fields);
			// Add contact
			if ( ! $contact['ID']) {
				if (!empty($cont_fields)) {
					$responsible_id = (int) $profile['options']['deal_respons_def'];
					if ($responsible_id) {
						$cont_fields['ASSIGNED_BY_ID'] = $responsible_id;
					}
					if ( ! $cont_fields['NAME'] && ! $cont_fields['LAST_NAME']) {
						$cont_fields['NAME'] = Loc::getMessage("SP_CI_SYNC_CONTACT_NAME_DEFAULT");
					}
					\SProdIntegration::Log('(syncOrderToDealContact) cont_fields for add ' . print_r($cont_fields, true));
					try {
						$contact_id = (int) Rest::execute('crm.contact.add', [
							'fields' => $cont_fields,
						]);
					} catch (\Exception $e) {
						\SProdIntegration::Log('(syncOrderToDealContact) add contact error ' . $e->getMessage() . ' [' . $e->getCode() . ']');
						CrmNotif::sendMsg(Loc::getMessage("SP_CI_SYNC_CONTACT_ADD_ERROR", ['#ERROR_MSG#'  => $e->getMessage(),
						                                                                   '#ERROR_CODE#' => $e->getCode()
						]), 'ERRCNTADD', CrmNotif::TYPE_ERROR);
					}
					if ($contact_id) {
						\SProdIntegration::Log('(syncOrderToDealContact) added contact id ' . $contact_id);
					} else {
						\SProdIntegration::Log('(syncOrderToDealContact) add contact error');
					}
				}
			} // Update contact
			else {
				$contact_id = $contact['ID'];
				if ((( ! $deal['ID'] && $sync_new_type == 1) || $sync_new_type == 2) && UpdateLock::isChanged($contact_id, 'contact_stoc', $cont_fields, true)) {
					\SProdIntegration::Log('(syncOrderToDealContact) cont_fields for update ' . print_r($cont_fields, true));
					Rest::execute('crm.contact.update', [
						'id'     => $contact_id,
						'fields' => $cont_fields,
					]);
				}
			}
			if ($contact_id) {
				$result = $contact_id;
			}
		}

		return $result;
	}

	/**
	 * Get contact data by profile
	 */
	public static function getDealContactDataByProfile(array $order_data, $contact, $profile) {
		$cont_fields = [];
		$person_type = $order_data['PERSON_TYPE_ID'];
		$comp_table = (array) $profile['contact']['comp_table'][$person_type];
		$user_fields = StoreUser::getById($order_data['USER_ID']);
		//\SProdIntegration::Log('(getDealContactDataByProfile) user_fields '.print_r($user_fields, true));
		foreach ($comp_table as $deal_f_id => $sync_params) {
			$order_f_id = $sync_params['value'];
			// User fields
			if ($order_f_id) {
				$value = false;
				if ( ! (int) $order_f_id) {
					$value = $user_fields[$order_f_id];
				} // Properties
				else {
					foreach ($order_data['PROPERTIES'] as $prop) {
						if ($prop['ID'] == $order_f_id) {
							if ($prop['TYPE'] == 'FILE') {
								if (isset($prop['VALUE'][0])) {
									$file = $prop['VALUE'][0];
									$value = Utilities::getFileFieldForCrm($_SERVER['DOCUMENT_ROOT'] . $file['SRC']);
								}
							} else {
								$value = $prop['VALUE'][0];
							}
						}
					}
				}
				if ($value) {
					if (in_array($deal_f_id, ['EMAIL', 'PHONE'])) {
						$phonemail_mode = Settings::get('contacts_phonemail_mode');
						if ($phonemail_mode == 'replace' && ! empty($contact[$deal_f_id])) {
							foreach ($contact[$deal_f_id] as $i => $item) {
								if ($i == 0) {
									$cont_fields[$deal_f_id][] = [
										'ID'         => $item['ID'],
										'VALUE'      => $value,
										'VALUE_TYPE' => 'WORK'
									];
								} else {
									$cont_fields[$deal_f_id][] = ['ID' => $item['ID'], 'DELETE' => 'Y'];
								}
							}
						} else {
							$cont_fields[$deal_f_id][] = ['VALUE' => $value, 'VALUE_TYPE' => 'WORK'];
						}
					} else {
						$cont_fields[$deal_f_id] = $value;
					}
				} else {
					$cont_fields[$deal_f_id] = '';
				}
			}
		}

		return $cont_fields;
	}

	public static function getPhonesFormats($phone) {
		$phones = [];
		if (strlen($phone)) {
			$phoneUnformatted = preg_replace('/[^+\d]/', '', $phone);
			$phoneFormatted1 = preg_replace(
				[
					'/^\+?7([\d]{3})([\d]{3})([\d]{2})([\d]{2})$/m',
					'/^8([\d]{3})([\d]{3})([\d]{2})([\d]{2})$/m',
					'/^\+?380([\d]{2})([\d]{3})([\d]{2})([\d]{2})$/m',
					'/^\+?996([\d]{3})([\d]{3})([\d]{3})$/m',
					'/^\+?998([\d]{2})([\d]{3})([\d]{4})$/m',
				],
				[
					'+7 (${1}) ${2}-${3}-${4}', // +7 (___) ___-__-__
					'+7 (${1}) ${2}-${3}-${4}', // +7 (___) ___-__-__
					'+380 (${1}) ${2}-${3}-${4}', // +380 (__) ___-__-__
					'+996 (${1}) ${2}-${3}', // +996 (___) ___-___
					'+998-${1}-${2}-${3}', // +998-__- ___-____
				],
				$phoneUnformatted
			);
			$phoneFormatted2 = preg_replace(
				[
					'/^\+?7([\d]{3})([\d]{3})([\d]{2})([\d]{2})$/m',
					'/^8([\d]{3})([\d]{3})([\d]{2})([\d]{2})$/m',
					'/^\+?380([\d]{2})([\d]{3})([\d]{2})([\d]{2})$/m',
					'/^\+?996([\d]{3})([\d]{3})([\d]{3})$/m',
					'/^\+?998([\d]{2})([\d]{3})([\d]{4})$/m',
				],
				[
					'+7${1}${2}${3}${4}', // 7__________
					'+7${1}${2}${3}${4}', // 7__________
					'+380${1}${2}${3}${4}', // 380__________
					'+996${1}${2}${3}', // 996__________
					'+998${1}${2}${3}', // 998__________
				],
				$phoneUnformatted
			);
			$phoneFormatted3 = preg_replace(
				[
					'/^\+?7([\d]{3})([\d]{3})([\d]{2})([\d]{2})$/m',
					'/^8([\d]{3})([\d]{3})([\d]{2})([\d]{2})$/m',
					'/^\+?380([\d]{2})([\d]{3})([\d]{2})([\d]{2})$/m',
					'/^\+?996([\d]{3})([\d]{3})([\d]{3})$/m',
					'/^\+?998([\d]{2})([\d]{3})([\d]{4})$/m',
				],
				[
					'7${1}${2}${3}${4}', // 7__________
					'7${1}${2}${3}${4}', // 7__________
					'380${1}${2}${3}${4}', // 380__________
					'996${1}${2}${3}', // 996__________
					'998${1}${2}${3}', // 998__________
				],
				$phoneUnformatted
			);
			$phones = array_unique([$phone, $phoneFormatted1, $phoneFormatted2, $phoneFormatted3, $phoneUnformatted]);
		}

		return $phones;
	}

	/**
	 * Check if needed of find new contact
	 */
	public static function syncOrderToDealContactNeedFindNew(array $order_data, $deal_info, $profile) {
		$result = false;
		if ( ! $deal_info['contact']['ID']
			|| (Settings::get("contacts_link_mode") == 'user_change' && $order_data['NEW_VALUES']['USER_ID'])) {
			$result = true;
		}

		return $result;
	}

}
