<?php
/**
 * Contact management for forgotten baskets
 * Adapted for FBasket profile structure
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
	Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class FbasketContacts
{
	const MODULE_ID = 'sproduction.integration';

	/**
	 * Get or create contact for basket user
	 */
	public static function getOrCreateContact($basket, $profile, $is_new = true, $existing_contact_id = null) {
		if (!$basket['USER_ID']) {
			return false;
		}

		$user_id = $basket['USER_ID'];

		// Get user data
		$user = StoreUser::getById($user_id);

		if (!$user) {
			\SProdIntegration::Log('(FbasketContacts::getOrCreateContact) user not found: ' . $user_id);
			return false;
		}

		// Prepare order data structure for contact search
		$order_data = [
			'USER_ID' => $user_id,
			'PERSON_TYPE_ID' => 1, // Physical person
			'PROPERTIES' => [],
		];

		// Add email property
		if (!empty($user['EMAIL'])) {
			$order_data['PROPERTIES'][] = ['ID' => 'EMAIL', 'VALUE' => [$user['EMAIL']], 'TYPE' => 'email'];
		}
		// Add phone property
		if (!empty($user['PHONE'])) {
			$order_data['PROPERTIES'][] = ['ID' => 'PHONE', 'VALUE' => [$user['PHONE']], 'TYPE' => 'string'];
		}

		// Find contact using FBasket-specific search method
		$contact = self::findContact($order_data, $profile);

		if ($contact && !empty($contact['ID'])) {
			\SProdIntegration::Log('(FbasketContacts::getOrCreateContact) found existing contact ' . $contact['ID'] . ' for user ' . $user_id);

			$contact_id = (int) $contact['ID'];

			// Update contact if needed based on sync_new_type option
			self::updateContactIfNeeded($contact, $order_data, $profile, $is_new);

			// If contact was already linked to the deal and its ID hasn't changed - don't return it
			if ($existing_contact_id && $contact_id === (int) $existing_contact_id) {
				\SProdIntegration::Log('(FbasketContacts::getOrCreateContact) contact ' . $contact_id . ' is already linked to deal, skipping');
				return false;
			}

			return $contact_id;
		}

		// Create new contact if not found
		$contact_id = self::createContact($order_data, $profile, $user_id);

		return $contact_id;
	}

	/**
	 * Create new contact
	 */
	protected static function createContact($order_data, $profile, $user_id) {
		// Get contact fields using FBasket-specific method
		$cont_fields = self::getContactDataByProfile($order_data, [], $profile);
		$cont_fields = Utilities::convEncForDeal($cont_fields);

		// Add user ID field
		$user_id_field = Settings::get('contact_user_id_field') ?: 'UF_CRM_USER_ID';
		$cont_fields[$user_id_field] = $user_id;

		// Set default name if empty
		if (!$cont_fields['NAME'] && !$cont_fields['LAST_NAME']) {
			$cont_fields['NAME'] = Loc::getMessage('SP_CI_SYNC_CONTACT_NAME_DEFAULT');
		}

		// Set responsible user
		$responsible_id = (int) $profile['options']['deal_respons_def'];
		if ($responsible_id) {
			$cont_fields['ASSIGNED_BY_ID'] = $responsible_id;
		}

		\SProdIntegration::Log('(FbasketContacts::createContact) creating contact with fields: ' . print_r($cont_fields, true));

		try {
			$contact_id = (int) Rest::execute('crm.contact.add', [
				'fields' => $cont_fields,
			]);

			if ($contact_id) {
				\SProdIntegration::Log('(FbasketContacts::createContact) created contact ' . $contact_id . ' for user ' . $user_id);
				return $contact_id;
			} else {
				\SProdIntegration::Log('(FbasketContacts::createContact) failed to create contact for user ' . $user_id);
			}
		} catch (\Exception $e) {
			\SProdIntegration::Log('(FbasketContacts::createContact) error creating contact: ' . $e->getMessage() . ' [' . $e->getCode() . ']');
		}

		return false;
	}

	/**
	 * Find existing contact for basket user (adapted for FBasket profiles)
	 */
	protected static function findContact(array $order_data, $profile) {
		$contact = false;
		if (Rest::checkConnection()) {
			// Get contact fields using FBasket-specific structure
			$cont_fields = self::getContactDataByProfile($order_data, [], $profile);
			
			// Get search field from FBasket profile structure
			$cont_s_field = isset($profile['contacts']['contact_search_fields']) 
				? $profile['contacts']['contact_search_fields'] 
				: '';
			
			if ($cont_s_field) {
				if (isset($cont_fields[$cont_s_field]) && $cont_fields[$cont_s_field]) {
					$search_value = is_array($cont_fields[$cont_s_field]) 
						? ($cont_fields[$cont_s_field][0]['VALUE'] ?? null) 
						: $cont_fields[$cont_s_field];
					
					if ($search_value) {
						$filter = [$cont_s_field => $search_value];
						$request = [
							'list' => [
								'method' => 'crm.contact.list',
								'params' => ['filter' => $filter],
								'start' => -1
							],
							'get'  => [
								'method' => 'crm.contact.get',
								'params' => ['id' => '$result[list][0][ID]']
							]
						];
						$res = Rest::batch($request);
						if ($res['get']) {
							$contact = $res['get'];
						}
					}
				}
			} else {
				// Search by phone and/or email if no custom search field
				$search_phone = null;
				$search_email = null;
				
				if (isset($cont_fields['PHONE']) && isset($cont_fields['PHONE'][0]) && isset($cont_fields['PHONE'][0]['VALUE'])) {
					$search_phone = $cont_fields['PHONE'][0]['VALUE'];
				}
				if (isset($cont_fields['EMAIL']) && isset($cont_fields['EMAIL'][0]) && isset($cont_fields['EMAIL'][0]['VALUE'])) {
					$search_email = $cont_fields['EMAIL'][0]['VALUE'];
				}
				
				// Find by phone
				if ($search_phone) {
					$phones = CrmContact::getPhonesFormats($search_phone);
					foreach ($phones as $phone) {
						$filter = ['PHONE' => $phone];
						$request = [
							'list' => [
								'method' => 'crm.contact.list',
								'params' => ['filter' => $filter],
								'start' => -1
							],
							'get'  => [
								'method' => 'crm.contact.get',
								'params' => ['id' => '$result[list][0][ID]']
							]
						];
						$res = Rest::batch($request);
						if (isset($res['get']['ID'])) {
							$contact = $res['get'];
							break;
						}
					}
				}
				// Find by email
				if (!$contact && $search_email) {
					$filter = ['EMAIL' => $search_email];
					$request = [
						'list' => [
							'method' => 'crm.contact.list',
							'params' => ['filter' => $filter],
							'start' => -1
						],
						'get'  => [
							'method' => 'crm.contact.get',
							'params' => ['id' => '$result[list][0][ID]']
						]
					];
					$res = Rest::batch($request);
					if (isset($res['get']['ID'])) {
						$contact = $res['get'];
					}
				}
				\SProdIntegration::Log('(FbasketContacts::findContact) found contact "' . ($contact['ID'] ?? 'null') . '" by filter: ' . print_r($filter ?? [], true));
			}
		}

		return $contact;
	}

	/**
	 * Update contact if needed based on sync_new_type option
	 */
	protected static function updateContactIfNeeded($contact, $order_data, $profile, $is_new = true) {
		$contact_id = (int) $contact['ID'];

		// Get sync_new_type option from profile
		$sync_new_type = isset($profile['contacts']['sync_new_type'])
			? (int) $profile['contacts']['sync_new_type']
			: 0;

		// sync_new_type values:
		// 0 - never update
		// 1 - update only for new baskets/deals
		// 2 - always update

		$should_update = false;

		if ($sync_new_type == 1 && $is_new) {
			$should_update = true;
		} elseif ($sync_new_type == 2) {
			$should_update = true;
		}

		if ($should_update) {
			self::updateContact($contact_id, $order_data, $profile);
		}
	}

	/**
	 * Update existing contact
	 */
	protected static function updateContact($contact_id, $order_data, $profile) {
		// Get contact fields using FBasket-specific method
		$cont_fields = self::getContactDataByProfile($order_data, [], $profile);
		$cont_fields = Utilities::convEncForDeal($cont_fields);

		\SProdIntegration::Log('(FbasketContacts::updateContact) updating contact ' . $contact_id . ' with fields: ' . print_r($cont_fields, true));

		try {
			Rest::execute('crm.contact.update', [
				'id' => $contact_id,
				'fields' => $cont_fields,
			]);

			\SProdIntegration::Log('(FbasketContacts::updateContact) successfully updated contact ' . $contact_id);
		} catch (\Exception $e) {
			\SProdIntegration::Log('(FbasketContacts::updateContact) error updating contact: ' . $e->getMessage() . ' [' . $e->getCode() . ']');
		}
	}

	/**
	 * Get contact data by profile (for FBasket profiles)
	 * Similar to CrmContact::getDealContactDataByProfile but adapted for FBasket profile structure
	 */
	protected static function getContactDataByProfile(array $order_data, $contact, $profile) {
		$cont_fields = [];
		$user_id = $order_data['USER_ID'];
		
		// FBasket profile structure: $profile['contacts']['comp_table']
		// Order profile structure: $profile['contact']['comp_table'][$person_type]
		$comp_table = [];
		if (isset($profile['contacts']['comp_table']) && is_array($profile['contacts']['comp_table'])) {
			$comp_table = $profile['contacts']['comp_table'];
		}
		
		$user_fields = StoreUser::getById($user_id);
		
		foreach ($comp_table as $deal_f_id => $sync_params) {
			$order_f_id = is_array($sync_params) ? ($sync_params['value'] ?? null) : $sync_params;
			
			// User fields
			if ($order_f_id) {
				$value = false;
				if (!is_numeric($order_f_id)) {
					// User field by field name
					$value = $user_fields[$order_f_id] ?? null;
				} else {
					// Properties - convert to numeric ID
					$order_f_id_int = (int) $order_f_id;
					foreach ($order_data['PROPERTIES'] as $prop) {
						if ($prop['ID'] == $order_f_id_int) {
							if (isset($prop['VALUE'][0])) {
								$value = $prop['VALUE'][0];
							}
							break;
						}
					}
				}
				
				if ($value) {
					if (in_array($deal_f_id, ['EMAIL', 'PHONE'])) {
						// EMAIL and PHONE require special format
						$cont_fields[$deal_f_id][] = ['VALUE' => $value, 'VALUE_TYPE' => 'WORK'];
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

}