<?php
/**
 * Remote diagnostics page
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

\Bitrix\Main\Loader::includeModule('sale');

// Include required classes
require_once(__DIR__ . '/fbasketprofiles.php');

use SProduction\Integration\ProfilesTable,
	SProduction\Integration\FbasketProfilesTable,
	SProduction\Integration\StoreData,
	SProduction\Integration\PortalData,
	SProduction\Integration\BoxMode,
	SProduction\Integration\SyncOrder,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale;

class RemoteDiag
{
	const MODULE_ID = 'sproduction.integration';

	public static function getMainInfo() {
		$result = [];
		// Engine info
		$info = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_INFO'),
			'list' => [],
		];
		$info['list'][] = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_INFO_VERSION'),
			'value' => self::formatValue(SM_VERSION),
		];
		$info['list'][] = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_INFO_CHARSET'),
			'value' => self::formatValue(SITE_CHARSET),
		];
		$module_info = \SProdIntegration::getModuleInfo();
		$info['list'][] = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_INFO_MODVERS'),
			'value' => self::formatValue($module_info['version']),
		];
		$incl_res = \Bitrix\Main\Loader::includeSharewareModule('sproduction.integration');
		$demo_modes = [
			\Bitrix\Main\Loader::MODULE_NOT_FOUND => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_INFO_MODE_1'),
			\Bitrix\Main\Loader::MODULE_INSTALLED => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_INFO_MODE_2'),
			\Bitrix\Main\Loader::MODULE_DEMO => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_INFO_MODE_3'),
			\Bitrix\Main\Loader::MODULE_DEMO_EXPIRED => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_INFO_MODE_4')
		];
		$info['list'][] = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_INFO_MODE'),
			'value' => self::formatValue($demo_modes[$incl_res]),
		];
		$module_const = 'sproduction_integration_OLDSITEEXPIREDATE';
		$expire_date_f = '';
		if (defined($module_const)) {
			$expire_info = getdate(constant($module_const));
			$expire_ts = gmmktime($expire_info['hours'], $expire_info['minutes'], $expire_info['seconds'], $expire_info['mon'], $expire_info['mday'], $expire_info['year']);
			$expire_date = date('d.m.Y H:i:s', $expire_ts);
			$expire_date_f = self::formatValue($expire_date);
		}
		$info['list'][] = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_INFO_EXPIRE_DEMO'),
			'value' => $expire_date_f,
		];
		$module_upd_info = \SProdIntegration::getUpdateInfo();
		if ($module_upd_info) {
			$info['list'][] = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_INFO_EXPIRE_LICENSE'),
				'value' => self::formatValue($module_upd_info['DATE_TO']),
			];
		}
		// PHP log
		$settings = include($_SERVER['DOCUMENT_ROOT'] . '/bitrix/.settings.php');
		if (isset($settings['exception_handling']['value']['log']['settings']['file'])) {
			$link = $settings['exception_handling']['value']['log']['settings']['file'];
			$info['list'][] = [
				'title' => Loc::getMessage('SP_DS_REMOTEDIAG_MAININFO_INFO_PHP_LOG'),
				'value' => $link,
			];
		}
		$result[] = $info;
		// Diagnostics
		$info = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_DIAG'),
			'list' => [],
		];
		$list = CheckState::checkList();
		if (is_array($list) && !empty($list)) {
			foreach ($list as $k => $value) {
				$info['list'][] = [
					'title' => $k,
					'value' => $value,
				];
			}
		}
		$result[] = $info;
		// Portal info
		$info = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_PORTAL'),
			'list' => [],
		];
		if (CheckState::checkConnection()) {
			$info['list'][] = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_PORTAL_APPINFO'),
				'value' => self::formatValue(Rest::execute('app.info')),
			];
			$info['list'][] = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_PORTAL_PROFILE'),
				'value' => self::formatValue(Rest::execute('profile')),
			];
			$info['list'][] = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_MAININFO_PORTAL_SCOPE'),
				'value' => self::formatValue(Rest::execute('scope')),
			];
		}
		$result[] = $info;
		return $result;
	}

	public static function getOptions() {
		$result = [];
		$info = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_OPTIONS_GENERAL'),
			'list' => [],
		];
		global $DB;
		$sql_query = "SELECT * FROM `b_option` WHERE `MODULE_ID` = 'sproduction.integration';";
		$db_res = $DB->Query($sql_query);
		$excludes = [
			'~bsm_stop_date',
		];
		while ($row = $db_res->Fetch()) {
			if ($row['NAME'] && !in_array($row['NAME'], $excludes)) {
				$info['list'][] = [
					'title' => $row['NAME'],
					'value' => self::formatValue($row['VALUE'], false),
				];
			}
		}
		$result[] = $info;
		return $result;
	}

	public static function getProfiles() {
		$result = [];
		$list = ProfilesTable::getList([
			'order'  => ['id' => 'asc'],
		]);
		foreach ($list as $item) {
			$info = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_PROFILES_PROFILE') . ' "' . $item['name'] . '"',
				'list' => [],
			];
			foreach ($item as $item_k => $item_value) {
				$info['list'][] = [
					'title' => $item_k,
					'value' => self::formatValue($item_value),
				];
			}
			$result[] = $info;
		}
		return $result;
	}

	public static function getFbasketProfiles() {
		$result = [];
		$list = FbasketProfilesTable::getList([
			'order'  => ['id' => 'asc'],
		]);
		foreach ($list as $item) {
			$info = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_FBASKET_PROFILES_PROFILE') . ' "' . $item['name'] . '"',
				'list' => [],
			];
			foreach ($item as $item_k => $item_value) {
				$info['list'][] = [
					'title' => $item_k,
					'value' => self::formatValue($item_value),
				];
			}
			$result[] = $info;
		}
		return $result;
	}

	public static function getStoreFields() {
		$result = [];
		$info = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_STORE_FIELDS_TITLE'),
			'list' => [],
		];
		$info['list'][]     = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_STORE_STATUSES'),
			'value' => self::formatValue(ProfileInfo::getSiteStatuses([])),
		];
		$info['list'][]     = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_STORE_PERSON_TYPES'),
			'value' => self::formatValue(ProfileInfo::getSitePersonTypes([])),
		];
		$info['list'][]     = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_STORE_PROPS'),
			'value' => self::formatValue(ProfileInfo::getSiteProps([])),
		];
		$info['list'][]     = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_STORE_OTHER_PROPS'),
			'value' => self::formatValue(ProfileInfo::getSiteOtherProps([])),
		];
		$info['list'][]     = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_STORE_CONTACT_FIELDS'),
			'value' => self::formatValue(ProfileInfo::getSiteContactFields([])),
		];
		$info['list'][]     = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_STORE_USER_GROUPS'),
			'value' => self::formatValue(ProfileInfo::getSiteUGroups([])),
		];
		$result[] = $info;
		return $result;
	}

	public static function getCRMFields() {
		$result = [];
		$info = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_CRM_FIELDS_TITLE'),
			'list' => [],
		];
		if (CheckState::checkConnection()) {
			$info['list'][] = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_CRM_FIELDS'),
				'value' => self::formatValue(Rest::execute('crm.deal.fields')),
			];
			$info['list'][] = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_CRM_DIRECTIONS'),
				'value' => self::formatValue(ProfileInfo::getCrmDirections([])),
			];
			$category_list = ProfileInfo::getCrmDirections([]);
			foreach ($category_list as $category_id => $title) {
				$profile['options']['deal_category'] = $category_id;
				$info['list'][] = [
					'title' => Loc::getMessage('SP_CI_REMOTEDIAG_CRM_STAGES', [
						'#CATEGORY_TITLE#' => $title,
						'#CATEGORY_ID#'    => $category_id
					]),
					'value' => self::formatValue(ProfileInfo::getCrmStages($profile)),
				];
			}
			$info['list'][] = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_CRM_CONTACT_FIELDS'),
				'value' => self::formatValue(ProfileInfo::getCrmContactFields([])),
			];
			$info['list'][] = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_CRM_CONTACT_SEARCH_FIELDS'),
				'value' => self::formatValue(ProfileInfo::getCrmContactSFields([])),
			];
			$info['list'][] = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_CRM_COMPANY_FIELDS'),
				'value' => self::formatValue(ProfileInfo::getCrmCompanyFields([])),
			];
			$info['list'][] = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_CRM_SOURCES'),
				'value' => self::formatValue(ProfileInfo::getCrmSources([])),
			];
			$info['list'][] = [
				'title' => Loc::getMessage('SP_CI_REMOTEDIAG_CRM_USERS'),
				'value' => self::formatValue(ProfileInfo::getCrmUsers([])),
			];
		}
		$result[] = $info;
		return $result;
	}

	public static function getHandlers() {
		$result = [];
		$info = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_HANDLERS_TITLE'),
			'list' => [],
		];
		$info['list'][] = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_HANDLERS_STORE'),
			'value' => self::formatValue(StoreHandlers::getList()),
		];
		$info['list'][] = [
			'title' => Loc::getMessage('SP_CI_REMOTEDIAG_HANDLERS_CRM'),
			'value' => self::formatValue(PortalHandlers::getList()),
		];
		$result[] = $info;
		return $result;
	}

	public static function getFilelog() {
		$result = [];
		$result['active'] = (Settings::get("filelog") == 'Y');
		$result['link'] = FileLogControl::getLink();
		$result['info'] = FileLogControl::get();
		return $result;
	}

	public static function getLogQueries() {
		$result = [];
		$result['active'] = (Settings::get("log_queries") == 'Y');
		return $result;
	}

	public static function formatValue($value, $simple=true) {
		$result = $value;
		if (!$simple) {
			$result = @unserialize($result);
			if ($result === false) {
				$result = $value;
			}
		}
		$result = print_r($result, true);
		$result = '<pre>' . $result . '</pre>';
		return $result;
	}

	/**
	 * Получить данные заказа по ID
	 * @param int $order_id
	 * @return array|null
	 */
	public static function getOrderData($order_id) {
		if (!$order_id) {
			return null;
		}
		$order = Sale\Order::load($order_id);
		if (!$order) {
			return null;
		}
		return StoreData::getOrderInfo($order);
	}

	/**
	 * Получить данные сделки по ID
	 * @param int $deal_id
	 * @return array|null
	 */
	public static function getDealData($deal_id) {
		if (!$deal_id) {
			return null;
		}
		$deals = PortalData::getDeal([$deal_id]);
		if (empty($deals) || !isset($deals[0])) {
			return null;
		}

		$deal = $deals[0];

		// Получаем товары сделки
		$products = Rest::execute('crm.deal.productrows.get', [
			'id' => $deal_id
		]);

		$deal['PRODUCTS'] = $products ?: [];

		return $deal;
	}

	/**
	 * Получить данные заказа и связанной сделки
	 * @param int $order_id
	 * @return array ['order' => array|null, 'deal' => array|null]
	 */
	public static function getOrderDataWithRelatedDeal($order_id) {
		if (!$order_id) {
			return [
				'order' => null,
				'deal' => null,
			];
		}

		$result = [
			'order' => null,
			'deal' => null,
		];
		
		// Получаем данные заказа
		$order_data = self::getOrderData($order_id);
		
		// Ищем связанную сделку
		if ($order_id && $order_data) {
			$result['order'] = $order_data;
			$profile = ProfilesTable::getOrderProfile($order_data);
			if ($profile) {
				$order_id = $order_data['ID'];
				if (Settings::getSyncMode() == Settings::SYNC_MODE_BOX) {
					$deal_id = BoxMode::findDealByOrder($order_id);
				}
				else {
					$deal_id = PortalData::findDeal($order_data, $profile);
				}
				if ($deal_id) {
					$result['deal'] = self::getDealData($deal_id);
				}
			}
		}
		
		return $result;
	}

	/**
	 * Получить данные сделки и связанного заказа
	 * @param int $deal_id
	 * @return array ['deal' => array|null, 'order' => array|null]
	 */
	public static function getDealDataWithRelatedOrder($deal_id) {
		if (!$deal_id) {
			return [
				'deal' => null,
				'order' => null,
			];
		}

		$result = [
			'deal' => null,
			'order' => null,
		];
		
		// Получаем данные сделки
		$result['deal'] = self::getDealData($deal_id);
		
		// Ищем связанный заказ
		if ($deal_id) {
			if (Settings::getSyncMode() == Settings::SYNC_MODE_BOX) {
				$order_id = BoxMode::findOrderByDeal($deal_id);
			}
			else {
				$order_id = SyncOrder::getOrderIdByDeal($result['deal']);
			}
			if ($order_id) {
				$result['order'] = self::getOrderData($order_id);
			}
		}
		
		return $result;
	}
}