<?php
/**
 * Synchronization of store user
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

class StoreUser
{
    const MODULE_ID = 'sproduction.integration';

	/**
	 * Get user data by id
	 */
	public static function getById(int $user_id) {
		$res = \CUser::GetByID($user_id);
		$user_fields = $res->Fetch();
		// Get phone
		if ($user_fields['ID']) {
			$phone_data = \Bitrix\Main\UserPhoneAuthTable::getList([
				'select' => ['PHONE_NUMBER'],
				'filter' => ['USER_ID' => $user_fields['ID']]
			])->fetch();
			if ($phone_data) {
				$user_fields['PHONE'] = $phone_data['PHONE_NUMBER'];
			}
		}
		return $user_fields;
	}

	public static function syncContactToBuyer($deal, $profile) {
		$user_id = false;
		// User data
		$user_fields = self::getBuyerFields($deal, $profile);
		if (!\SProdIntegration::isUtf()) {
			$user_fields = \Bitrix\Main\Text\Encoding::convertEncoding($user_fields, "UTF-8", "Windows-1251");
		}
		\SProdIntegration::Log('(syncContactToBuyer) fields for user ' . print_r($user_fields, true));
		// Search user
		if (!empty($user_fields)) {
			$user_id = self::findBuyer($user_fields);
		}
		\SProdIntegration::Log('(syncContactToBuyer) found user "' . $user_id . '"');
		// Create user
		if (!$user_id) {
			$user_id = self::createBuyer($user_fields, $profile);
			\SProdIntegration::Log('(syncContactToBuyer) created user "' . $user_id . '"');
		}
		// Default buyer
		if (!$user_id) {
			$user_id = $profile['neworder']['buyer_def'];
			\SProdIntegration::Log('(syncContactToBuyer) set default user "' . $user_id . '"');
		}
		return $user_id;
	}

	/**
	 * Get fields for search or generate buyer
	 */

	public static function getBuyerFields(array $deal, $profile) {
		$buyer_fields = [];
		$contact_id = $deal['CONTACT_ID'];
		$user_fields = CrmContact::getById($contact_id);
		$comp_table = (array)$profile['neworder']['buyer_comp_table'];
		foreach ($comp_table as $order_f_id => $ext_f) {
			$ext_f_id = $ext_f['value'];
			// User fields
			if ($ext_f_id) {
				$value = $user_fields[$ext_f_id];
				if ($value) {
					if ($ext_f_id == 'EMAIL' || $ext_f_id == 'PHONE') {
						$buyer_fields[$order_f_id] = $value[0]['VALUE'];
					}
					else {
						$buyer_fields[$order_f_id] = $value;
					}
				} else {
					$buyer_fields[$order_f_id] = '';
				}
			}
		}
		// Default values
//		$buyer_fields['EMAIL'] = $buyer_fields['EMAIL'] ? : $profile['neworder']['buyer_default_email'];
		if ( ! $buyer_fields['LOGIN']) {
			if (isset($buyer_fields['PHONE']) && $buyer_fields['PHONE']) {
				$buyer_fields['LOGIN'] = $buyer_fields['PHONE'];
			}
			else {
				$buyer_fields['LOGIN'] = $buyer_fields['EMAIL'] ?? '';
			}
		}
		return $buyer_fields;
	}


	/**
	 * Try to find a buyer by login, phone or email
	 */

	public static function findBuyer(array $fields) {
		$user_id = false;
		if ($fields['LOGIN']) {
			$db_user = \Bitrix\Main\UserTable::getList(array(
				'select' => ['ID'],
				'filter' => ['LOGIN' => $fields['LOGIN']]
			));
			if ($user_data = $db_user->fetch()) {
				$user_id = $user_data['ID'];
			}
		}
		if (!$user_id && $fields['PHONE']) {
			$phone = \Bitrix\Main\PhoneNumber\Parser::getInstance()->parse($fields['PHONE']);
			$phone_data = \Bitrix\Main\UserPhoneAuthTable::getList(array(
				'select' => ['USER_ID'],
				'filter' => ['PHONE_NUMBER' => $phone->format(\Bitrix\Main\PhoneNumber\Format::E164)]
			))->fetch();
			if ($phone_data) {
				$user_id = $phone_data['USER_ID'];
			}
		}
		if (!$user_id && $fields['EMAIL']) {
			$db_user = \Bitrix\Main\UserTable::getList(array(
				'select' => ['ID'],
				'filter' => ['EMAIL' => $fields['EMAIL']]
			));
			if ($user_data = $db_user->fetch()) {
				$user_id = $user_data['ID'];
			}
		}
		return $user_id;
	}


	/**
	 * Create new buyer
	 */

	public static function createBuyer(array $fields, array $profile) {
		$user_id = false;
		$fields['EMAIL'] = $fields['EMAIL'] ? : $profile['neworder']['buyer_default_email'];
		if (!empty($fields)) {
			if (!$fields['LOGIN']) {
				$fields['LOGIN'] = $fields['EMAIL'] ? : $fields['PHONE'];
			}
			if (!$fields['EMAIL'] && $fields['PHONE']) {
				$phone = \Bitrix\Main\PhoneNumber\Parser::getInstance()->parse($fields['PHONE']);
				$fields['EMAIL'] = intval($phone->format(\Bitrix\Main\PhoneNumber\Format::E164)) . '@defaultmail.com';
			}
			if ($fields['PHONE']) {
				$phone = \Bitrix\Main\PhoneNumber\Parser::getInstance()->parse($fields['PHONE']);
				$fields['PHONE_NUMBER'] = $phone->format(\Bitrix\Main\PhoneNumber\Format::E164);
			}
			$fields['PASSWORD'] = md5($fields['EMAIL'] . rand(1000, 9999));
			$user = new \CUser;
			$user_id = $user->Add($fields);
			if (!intval($user_id)) {
				\SProdIntegration::Log('(createBuyer) error ' . $user->LAST_ERROR);
			}
		}
		return $user_id;
	}

	/**
	 * Find store user for deal assigned user
	 */
	public static function findByDealAssigned(array $deal_info) {
		$store_user_id = false;
		if ($deal_info['assigned_user']['EMAIL']) {
			$user_email = $deal_info['assigned_user']['EMAIL'];
			$res = \Bitrix\Main\UserTable::getList([
				'select' => ['ID'],
				'filter' => [
					'EMAIL' => $user_email,
				],
			]);
			if ($user = $res->fetch()) {
				$store_user_id = $user['ID'];
			}
		}
		return $store_user_id;
	}

	/**
	 * Find user
	 */
	public static function findUsers($search='', $limit=0) {
		$result = [];
		$fields = [
			'select' => ['ID', 'SHORT_NAME', 'EMAIL'],
			'order' => ['ID' => 'DESC'],
		];
		if ($limit) {
			$fields['limit'] = $limit;
		}
		if ($search) {
			if (is_numeric($search)) {
				$fields['filter'] = [
					'ID' => $search,
				];
			}
			else {
				$fields['filter'][] = [
					'LOGIC'      => 'OR',
					'SHORT_NAME' => $search . '%',
					'EMAIL'      => $search . '%',
				];
			}
		}
		$db = \Bitrix\Main\UserTable::getList($fields);
		while ($item = $db->fetch()) {
			$result[] = [
				'id' => $item['ID'],
				'name' => $item['SHORT_NAME'],
				'email' => $item['EMAIL'],
			];
		}
		return $result;
	}

}
