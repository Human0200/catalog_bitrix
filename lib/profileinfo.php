<?php
/**
 *    ProfileInfo
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

\Bitrix\Main\Loader::includeModule('sale');

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option,
	Bitrix\Main\Localization\Loc,
	SProduction\Integration\Rest;

Loc::loadMessages(__FILE__);

class ProfileInfo
{
	const PROPS_AVAILABLE = ['STRING', 'LOCATION', 'ENUM', 'Y/N', 'DATE', 'FILE', 'NUMBER'];
	const DEAL_FIELDS_TYPE_STRING = 'string';
	const DEAL_FIELDS_TYPE_INTEGER = 'integer';
	const DEAL_FIELDS_TYPE_FLOAT = 'double';
	const DEAL_FIELDS_TYPE_LIST = 'enumeration';
	const DEAL_FIELDS_TYPE_DATE = 'date';
	const DEAL_FIELDS_TYPE_DATETIME = 'datetime';
	const DEAL_FIELDS_TYPE_YN = 'boolean';
	const DEAL_FIELDS_TYPE_LINK = 'url';
	const DEAL_FIELDS_TYPE_FILE = 'file';
	const DEAL_FIELDS_TYPE_CRM_MF = 'crm_multifield';
	const SYNC_NONE = 0;
	const SYNC_STOC = 1;
	const SYNC_CTOS = 2;
	const SYNC_ALL = 3;
	const DATE_FORMAT_PORTAL = 'Y-m-d\TH:i:sO';
	const DATE_FORMAT_PORTAL_SHORT = 'Y-m-d';

	private static $CACHE = [];
	private static $CRM_DATA = [];
	private static $crm_iblock_list = [];


	/**
	 * Get all data
	 */
	public static function getAll($profile_id) {
		$info = [];
		if ($profile_id) {
			$profile = ProfilesTable::getById($profile_id);
			if ($profile) {
				self::preloadCrmData($profile);
				$info['crm']['users']      = self::getCrmUsers($profile);
				$info['crm']['directions'] = self::getCrmDirections($profile);
				$info['crm']['stages']     = self::getCrmStages($profile);
				$info['crm']['fields']     = self::getCrmFields($profile);
				$info['crm']['contact_fields'] = self::getCrmContactFields($profile);
				$info['crm']['contact_search_fields'] = self::getCrmContactSFields($profile);
				$info['crm']['company_fields'] = self::getCrmCompanyFields($profile);
				$info['crm']['sources'] = self::getCrmSources();
				$info['crm']['neworder_conds'] = self::getNeworderConditions($profile);
				$info['site']['user_groups']     = self::getSiteUGroups($profile);
				$info['site']['statuses']  = self::getSiteStatuses($profile);
				$info['site']['person_types'] = self::getSitePersonTypes($profile);
				$info['site']['props']     = self::getSiteProps($profile);
				$info['site']['other_props']     = self::getSiteOtherProps($profile);
				$info['site']['contact_fields'] = self::getSiteContactFields($profile);
				$info['site']['conditions'] = self::getFiltConditions($profile);
				$info['site']['deliv_types'] = self::getSiteDeliveryMethods();
				$info['site']['pay_types'] = self::getSitePayMethods();
				$info['site']['buyer_fields'] = self::getSiteBuyerFields();
				$info['site']['sites'] = self::getSitesList();
			}
		}
		return $info;
	}

	/**
	 * Requests cache
	 */
	protected static function addCache($method, $params, $value) {
		$params_hash = md5(serialize($params));
		self::$CACHE[$method . $params_hash] = $value;
	}
	protected static function getCache($method, $params) {
		$result = false;
		$params_hash = md5(serialize($params));
		if (isset(self::$CACHE[$method . $params_hash]) && !is_null(self::$CACHE[$method . $params_hash])) {
			$result = self::$CACHE[$method . $params_hash];
		}
		return $result;
	}

	/**
	 * Data preload
	 */
	public static function preloadCrmData($profile) {
		$req_list = [];
		// Directions
		$req_list['dealcategory_list'] = [
			'method' => 'crm.dealcategory.list',
			'params' => [
				'IS_LOCKED' => 'N',
			]
		];
		// Statuses
		$dealcateg_id = (int)$profile['options']['deal_category'];
		if (!$dealcateg_id) {
			$req_list['stage_list'] = [
				'method' => 'crm.status.list',
				'params' => [
					'order' => ['SORT' => 'ASC'],
					'filter' => [
						'ENTITY_ID' => 'DEAL_STAGE',
					]
				]
			];
		}
		else {
			$req_list['stage_list'] = [
				'method' => 'crm.dealcategory.stage.list',
				'params' => [
					'id' => $dealcateg_id,
				]
			];
		}
		// Fields list of deals
		$req_list['deal_fields'] = [
			'method' => 'crm.deal.fields',
			'params' => []
		];
		// List of catalogs
		$req_list['catalog_list'] = [
			'method' => 'catalog.catalog.list',
			'params' => []
		];
		// Contacts fields
		$req_list['contact_fields'] = [
			'method' => 'crm.contact.fields',
			'params' => []
		];
		// Contacts fields
		$req_list['company_fields'] = [
			'method' => 'crm.company.fields',
			'params' => []
		];
		// Requisite presets list
		$req_list['requisite_preset_list'] = [
			'method' => 'crm.requisite.preset.list',
			'params' => []
		];
		// Requisite presets list
		$req_list['source_list'] = [
			'method' => 'crm.status.list',
			'params' => [
				'sort' => ['SORT' => 'ASC'],
				'filter' => ['ENTITY_ID' => 'SOURCE'],
			]
		];
		// Get data
		self::$CRM_DATA = Rest::batch($req_list);
	}

	public static function getPreloadData($code) {
		return isset(self::$CRM_DATA[$code]) ? self::$CRM_DATA[$code] : null;
	}


	// Deals directions
	public static function getCrmDirections($profile) {
		$result = [
			0 => Loc::getMessage("SP_CI_MAIN_CATEGORY"),
		];
		if (self::getPreloadData('dealcategory_list')) {
			$list = self::getPreloadData('dealcategory_list');
		}
		else {
			$list = Rest::execute('crm.dealcategory.list', [
				'IS_LOCKED' => 'N',
			]);
		}
		if (is_array($list)) {
			foreach ($list as $item) {
				$name = $item['NAME'];
				if (!\SProdIntegration::isUtf()) {
					$name = \Bitrix\Main\Text\Encoding::convertEncoding($name, "UTF-8", "Windows-1251");
				}
				$result[$item['ID']] = $name;
			}
		}
		return $result;
	}

	// Portal users
	public static function getCrmUsers($profile) {
		$result = [];
		$params = [
			'sort' => 'LAST_NAME',
			'order' => 'asc',
			'FILTER' => [
				'ACTIVE' => 'Y',
				'USER_TYPE' => 'employee',
			],
		];
		$resp = Rest::getList('user.get', '', $params);
		if (!empty($resp)) {
			foreach ($resp as $item) {
				$result[$item['ID']] = $item['LAST_NAME'].' '.$item['NAME'].' ('.$item['EMAIL'].')';
			}
		}
		$result = Utilities::convEncForOrder($result);
		return $result;
	}

	// Deal stages
	public static function getCrmStages($profile) {
		global $APPLICATION;
		$result = [];
		if (self::getPreloadData('stage_list')) {
			$list = self::getPreloadData('stage_list');
		}
		else {
			$dealcateg_id = (int) $profile['options']['deal_category'];
			if ( ! $dealcateg_id) {
				$list = Rest::execute('crm.status.list', [
					'order'  => ['SORT' => 'ASC'],
					'filter' => [
						'ENTITY_ID' => 'DEAL_STAGE',
					]
				]);
			} else {
				$list = Rest::execute('crm.dealcategory.stage.list', [
					'id' => $dealcateg_id,
				]);
			}
		}
		if (is_array($list)) {
			foreach ($list as $item) {
				$result[] = [
					'id' => $item['STATUS_ID'],
					'name' => $item['NAME'],
				];
			}
		}
		if (strtolower(LANG_CHARSET) == 'windows-1251') {
			$result = $APPLICATION->ConvertCharset($result, "UTF-8", "CP1251");
		}
		return $result;
	}

	/**
	 * Deal fields
	 */
	public static function getCrmFields($profile, $field_types=[], $field_multiple=false) {
		global $APPLICATION;
		$result = [];
		// Additional fields
		$f_list_main = [];
		$f_list_main[] = [
			'id' => 'ID',
			'title' => Utilities::convEncToUtf(Loc::getMessage("SP_CI_MAIN_CRM_FIELDS_ID")),
			'type' => self::DEAL_FIELDS_TYPE_INTEGER,
			'multiple' => false,
		];
		$f_list_main[] = [
			'id' => 'LINK',
			'title' => Utilities::convEncToUtf(Loc::getMessage("SP_CI_MAIN_CRM_FIELDS_LINK")),
			'type' => self::DEAL_FIELDS_TYPE_STRING,
			'multiple' => false,
		];
		// Main fields
		$f_list = self::getCrmFieldsByRequest($profile);
		$f_list = array_merge($f_list_main, $f_list);
		// Result list
		foreach ($f_list as $item) {
			$f_disable = false;
			if (!empty($field_types) && !in_array($item['type'], $field_types)) {
				$f_disable = true;
			}
			if ($field_multiple == 'n' && $item['multiple']) {
				$f_disable = true;
			}
			if ($field_multiple == 'y' && !$item['multiple']) {
				$f_disable = true;
			}
			$result[] = [
				'id' => $item['id'],
				'title' => $item['title'],
				'disabled' => $f_disable,
			];
		}
		if (strtolower(LANG_CHARSET) == 'windows-1251') {
			$result = $APPLICATION->ConvertCharset($result, "UTF-8", "CP1251");
		}
		return $result;
	}

	public static function getCrmFieldsByRequest($profile) {
		$f_list = self::getCache('getCrmFieldsByRequest', []);
		if ($f_list === false) {
			$f_list = [];
			// System fields
			if (self::getPreloadData('deal_fields')) {
				$list = self::getPreloadData('deal_fields');
			}
			else {
				$list = Rest::execute('crm.deal.fields');
			}
			if ( ! empty($list)) {
				foreach ($list as $f_code => $item) {
					if (strpos($f_code, 'UTM_') === 0 || strpos($f_code, 'UF_CRM_') === 0 ||
						in_array($f_code, ['COMMENTS', 'BEGINDATE'])) {
						$f_list[] = [
							'id'       => $f_code,
							'title'    => $item['isDynamic'] ? $item['formLabel'] : $item['title'],
							'type'     => $item['type'],
							'multiple' => $item['isMultiple'],
						];
					}
				}
			}
			self::addCache('getCrmFieldsByRequest', [], $f_list);
		}
		return $f_list;
	}

	/**
	 * CRM catalog iblocks list
	 */
	public static function getCrmIblockList() {
		$list = [];
		if (!empty(self::$crm_iblock_list)) {
			$list = self::$crm_iblock_list;
		}
		else {
			if (self::getPreloadData('catalog_list')) {
				$resp = self::getPreloadData('catalog_list');
			}
			else {
				$resp = Rest::execute('catalog.catalog.list');
			}
			if (!\SProdIntegration::isUtf()) {
				$resp = \Bitrix\Main\Text\Encoding::convertEncoding($resp, "UTF-8", "Windows-1251");
			}
			$catalogs = [];
			if ($resp['catalogs']) {
				$catalogs = $resp['catalogs'];
			}
			foreach ($catalogs as $item) {
				$list[] = [
					'id'   => $item['iblockId'],
					'name' => $item['name'],
				];
			}
			self::$crm_iblock_list = $list;
		}
		return $list;
	}

	// Order statuses
	public static function getSiteStatuses($profile) {
		$result = [];
		$filter = [
			'LID' => LANGUAGE_ID,
			'TYPE' => 'O',
		];
		$select = ['ID', 'NAME'];
		$db = \CSaleStatus::GetList(['SORT' => 'ASC'], $filter, false, false, $select);
		while ($item = $db->Fetch()) {
			$result[] = [
				'id' => $item['ID'],
				'name' => $item['NAME'],
			];
		}
		return $result;
	}

	// List of person types
	public static function getSitePersonTypes($profile) {
		$result = [];
		$filter = [];
		$select = ['ID', 'NAME'];
		$db = \CSalePersonType::GetList([], $filter, false, false, $select);
		while ($item = $db->Fetch()) {
			$result[$item['ID']] = $item['NAME'];
		}
		return $result;
	}

	// Order properties
	public static function getSiteProps($profile) {
		$result = [];
		$db = \Bitrix\Sale\Property::getList([
			'order' => ['ID' => 'asc'],
			'select' => ['ID', 'NAME', 'PERSON_TYPE_ID', 'TYPE', 'MULTIPLE'],
		]);
		while ($prop = $db->Fetch()) {
			// Check props sync availibility
			if (!in_array($prop['TYPE'], self::PROPS_AVAILABLE)) {
				continue;
			}
			$prop['SYNC_DIR'] = self::SYNC_ALL;
			$prop['types'] = [];
			$prop['multiple'] = ($prop['MULTIPLE'] == 'Y') ? 'y' : false;
			switch ($prop['TYPE']) {
				case 'FILE':
					$prop['SYNC_DIR'] = self::SYNC_ALL;
					if ($prop['MULTIPLE'] == 'Y') {
						//continue 2;
					}
					$prop['types'] = [self::DEAL_FIELDS_TYPE_FILE];
					break;
//				case 'CHECKBOX':
//				case 'RADIO':
				case 'Y/N':
					if ($prop['MULTIPLE'] == 'Y') {
						continue 2;
					}
					$prop['types'] = [self::DEAL_FIELDS_TYPE_YN];
					$prop['multiple'] = 'n';
					break;
				case 'LOCATION':
					$prop['SYNC_DIR'] = self::SYNC_STOC;
					$prop['types'] = [self::DEAL_FIELDS_TYPE_STRING];
					break;
				case 'DATE':
					$prop['types'] = [self::DEAL_FIELDS_TYPE_DATE, self::DEAL_FIELDS_TYPE_DATETIME];
					break;
				case 'ENUM':
					$prop['types'] = [self::DEAL_FIELDS_TYPE_LIST];
					break;
				case 'NUMBER':
					$prop['types'] = [self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FLOAT,
						self::DEAL_FIELDS_TYPE_STRING];
					break;
				default:
					$prop['types'] = [self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FLOAT,
						self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LINK];
			}
			// Hints
			$prop['HINT'] = Loc::getMessage("SP_CI_PROP_".$prop['TYPE']."_HINT");
			// CRM values
			$prop['values'] = self::getCrmFields($profile, $prop['types'], $prop['multiple']);
			// Add to the result
			$result[$prop['PERSON_TYPE_ID']][] = $prop;
		}
		return $result;
	}

	// Payment and delivery
	public static function getSitePayDeliv($profile) {
		$result = [];
		// Payment type
		$result[] = [
			'ID' => 'PAY_TYPE',
			'NAME' => Loc::getMessage("SP_CI_SPOSOB_OPLATY"),
			'SYNC_DIR' => self::SYNC_STOC,
		];
		// Payment status
		$result[] = [
			'ID' => 'PAY_STATUS',
			'NAME' => Loc::getMessage("SP_CI_STATUS_OPLATY"),
			'SYNC_DIR' => self::SYNC_STOC,
		];
		// Delivery type
		$result[] = [
			'ID' => 'DELIV_TYPE',
			'NAME' => Loc::getMessage("SP_CI_SPOSOB_DOSTAVKI"),
			'SYNC_DIR' => self::SYNC_STOC,
		];
		return $result;
	}

	// Other properties
	public static function getSiteOtherProps($profile) {
		$result = [];
		$result[0] = [
			'title' => Loc::getMessage("SP_CI_PROFILE_OTHER_G_USER_ACCOUNT"),
			'items' => [],
		];
		$result[1] = [
			'title' => Loc::getMessage("SP_CI_PROFILE_OTHER_G_MAIN"),
			'items' => [],
		];
		$result[2] = [
			'title' => Loc::getMessage("SP_CI_PROFILE_OTHER_G_DELIVERY"),
			'items' => [],
		];
		$result[3] = [
			'title' => Loc::getMessage("SP_CI_PROFILE_OTHER_G_PAY"),
			'items' => [],
		];
		$result[4] = [
			'title' => Loc::getMessage("SP_CI_PROFILE_OTHER_G_OTHER"),
			'items' => [],
		];
		// User account data
		$result[0]['items'][] = [
			'ID' => 'USER_ID',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_STRING],
		];
		$result[0]['items'][] = [
			'ID' => 'USER_NAME',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		$result[0]['items'][] = [
			'ID' => 'USER_LAST_NAME',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		$result[0]['items'][] = [
			'ID' => 'USER_SECOND_NAME',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		$result[0]['items'][] = [
			'ID' => 'USER_EMAIL',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		$result[0]['items'][] = [
			'ID' => 'USER_PHONE',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		$result[0]['items'][] = [
			'ID' => 'USER_GROUPS_IDS',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LIST, self::DEAL_FIELDS_TYPE_INTEGER],
		];
		$result[0]['items'][] = [
			'ID' => 'USER_GROUPS_NAMES',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LIST],
		];
		// Order ID
		$result[1]['items'][] = [
			'ID' => 'ORDER_ID',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FLOAT, self::DEAL_FIELDS_TYPE_STRING],
		];
		// Order number
		$result[1]['items'][] = [
			'ID' => 'ORDER_NUMBER',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		// Order status code
		$result[1]['items'][] = [
			'ID' => 'ORDER_STATUS',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LIST],
		];
		// Order status name
		$result[1]['items'][] = [
			'ID' => 'ORDER_STATUS_NAME',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LIST],
		];
		// User type
		$result[1]['items'][] = [
			'ID' => 'USER_TYPE',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		// Date created
		$result[1]['items'][] = [
			'ID' => 'DATE_CREATE',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_DATE],
		];
		// Site ID
		$result[1]['items'][] = [
			'ID' => 'SITE_ID',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LIST],
		];
		// Delivery data
		$result[2]['items'][] = [
			'ID' => 'DELIV_TYPE',
			'SYNC_DIR' => self::SYNC_ALL,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LIST],
		];
		// Company name for delivery type
		$result[2]['items'][] = [
			'ID' => 'DELIVERY_COMPANY_NAME',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LIST],
		];
		$result[2]['items'][] = [
			'ID' => 'DELIVERY_STORE',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LIST],
		];
		$result[2]['items'][] = [
			'ID' => 'DELIVERY_PRICE',
			'SYNC_DIR' => self::SYNC_ALL,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FLOAT],
		];
		$result[2]['items'][] = [
			'ID' => 'DELIVERY_PRICE_CALCULATE',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FLOAT],
		];
		$result[2]['items'][] = [
			'ID' => 'DELIVERY_STATUS',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LIST],
		];
		$result[2]['items'][] = [
			'ID' => 'DELIVERY_STATUS_NAME',
			'SYNC_DIR' => self::SYNC_ALL,
			'types' => [self::DEAL_FIELDS_TYPE_LIST],
		];
		$result[2]['items'][] = [
			'ID' => 'DELIVERY_ALLOW',
			'SYNC_DIR' => self::SYNC_ALL,
			'types' => [self::DEAL_FIELDS_TYPE_YN],
		];
		$result[2]['items'][] = [
			'ID' => 'DELIVERY_DEDUCTED',
			'SYNC_DIR' => self::SYNC_ALL,
			'types' => [self::DEAL_FIELDS_TYPE_YN],
		];
		// Delivery type
		$result[2]['items'][] = [
			'ID' => 'DELIV_TRACKNUM',
			'SYNC_DIR' => self::SYNC_ALL,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		// Total amount
		$result[3]['items'][] = [
			'ID' => 'PAY_SUM',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FLOAT],
		];
		// Actually paid amount
		$result[3]['items'][] = [
			'ID' => 'PAY_FACT',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FLOAT],
		];
		// The remaining amount
		$result[3]['items'][] = [
			'ID' => 'PAY_LEFT',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FLOAT],
		];
		// Payment type
		$result[3]['items'][] = [
			'ID' => 'PAY_TYPE',
			'SYNC_DIR' => self::SYNC_ALL,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LIST],
		];
		// Payment ID
		$result[3]['items'][] = [
			'ID' => 'PAY_ID',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FLOAT],
		];
		// Payment status
		$result[3]['items'][] = [
			'ID' => 'PAY_STATUS',
			'SYNC_DIR' => self::SYNC_ALL,
			'types' => [self::DEAL_FIELDS_TYPE_YN],
		];
		// Payment date
		$result[3]['items'][] = [
			'ID' => 'PAY_DATE',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_DATE],
		];
		// Payment number
		$result[3]['items'][] = [
			'ID' => 'PAY_NUM',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		// Coupon
		$result[3]['items'][] = [
			'ID' => 'COUPON',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		// Order link
		$result[4]['items'][] = [
			'ID' => 'ORDER_LINK',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		// Order public link
		$result[4]['items'][] = [
			'ID' => 'ORDER_LINK_PUBLIC',
			'SYNC_DIR' => self::SYNC_STOC,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		// User comment
		$result[4]['items'][] = [
			'ID' => 'USER_DESCRIPTION',
			'SYNC_DIR' => self::SYNC_ALL,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		// Manager comment
		$result[4]['items'][] = [
			'ID' => 'COMMENTS',
			'SYNC_DIR' => self::SYNC_ALL,
			'types' => [self::DEAL_FIELDS_TYPE_STRING],
		];
		// Other data
		foreach ($result as $j => $group) {
			foreach ($group['items'] as $k => $prop) {
				$result[$j]['items'][$k]['NAME'] = Loc::getMessage('SP_CI_' . $prop['ID']);
				$result[$j]['items'][$k]['HINT'] = Loc::getMessage('SP_CI_OTHER_PROP_' . $prop['ID'] . '_HINT');
				// CRM values
				$result[$j]['items'][$k]['values'] = self::getCrmFields($profile, $prop['types']);
			}
		}
		return $result;
	}

	// Site users group
	public static function getSiteUGroups($profile) {
		$result = [];
		$filter = [];
		$db = \CGroup::GetList(($by="c_sort"), ($order="asc"), $filter);
		while ($item = $db->Fetch()) {
			$result[$item['ID']] = $item['NAME'];
		}
		return $result;
	}

	/**
	 * List of delivery methods
	 */
	public static function getSiteDeliveryMethods() {
		$result = [];
		$filter = ['ACTIVE' => 'Y'];
		$select = ['ID', 'NAME'];
		$db_res = \Bitrix\Sale\Delivery\Services\Table::getList([
			'filter'  => $filter,
			'select'  => $select,
		]);
		while ($item = $db_res->fetch()) {
			$result[] = [
				'id' => $item['ID'],
				'name' => $item['NAME'],
			];
		}
		return $result;
	}

	/**
	 * List of pay methods
	 */
	public static function getSitePayMethods() {
		$result = [];
		$filter = ['ACTIVE' => 'Y'];
		$select = ['ID', 'NAME'];
		$db_res = \Bitrix\Sale\PaySystem\Manager::getList([
			'filter'  => $filter,
			'select'  => $select,
		]);
		while ($item = $db_res->fetch()) {
			$result[] = [
				'id' => $item['ID'],
				'name' => $item['NAME'],
			];
		}
		return $result;
	}

	/**
	 * List of buyer fields
	 */
	public static function getSiteBuyerFields() {
		$result = [];
		$result[] = [
			'id' => 'LOGIN',
			'name' => Loc::getMessage("SP_CI_INFO_SITE_BUYER_FIELDS_LOGIN"),
		];
		$result[] = [
			'id' => 'LAST_NAME',
			'name' => Loc::getMessage("SP_CI_INFO_SITE_BUYER_FIELDS_LAST_NAME"),
		];
		$result[] = [
			'id' => 'NAME',
			'name' => Loc::getMessage("SP_CI_INFO_SITE_BUYER_FIELDS_NAME"),
		];
		$result[] = [
			'id' => 'SECOND_NAME',
			'name' => Loc::getMessage("SP_CI_INFO_SITE_BUYER_FIELDS_SECOND_NAME"),
		];
		$result[] = [
			'id' => 'EMAIL',
			'name' => Loc::getMessage("SP_CI_INFO_SITE_BUYER_FIELDS_EMAIL"),
		];
		$result[] = [
			'id' => 'PHONE',
			'name' => Loc::getMessage("SP_CI_INFO_SITE_BUYER_FIELDS_PHONE"),
		];
		return $result;
	}

	/**
	 * List of buyer fields
	 */
	public static function getSitesList() {
		$result = [];
		$res = \Bitrix\Main\SiteTable::getList([]);
		while ($site = $res->fetch()) {
			$result[] = [
				'id' => $site['LID'],
				'name' => $site['NAME'],
			];
		}
		return $result;
	}

	/**
	 * List of VAT variants of store
	 */
	public static function getSiteVatVariants() {
		$result = [];
		$filter = ['ACTIVE' => 'Y'];
		$select = ['ID', 'NAME'];
		$db_res = \Bitrix\Catalog\VatTable::getList([
			'filter'  => $filter,
			'select'  => $select,
		]);
		while ($item = $db_res->fetch()) {
			$result[] = [
				'id' => $item['ID'],
				'name' => $item['NAME'],
			];
		}
		return $result;
	}

	// Filter conditions
	public static function getFiltConditions($profile) {
		$result = [];
		// Site of order
		$result['site'] = [
			'title' => Loc::getMessage("SP_CI_SITE"),
			'items' => [],
			'values' => [],
		];
		$res = \Bitrix\Main\SiteTable::getList([]);
		while ($site = $res->fetch()) {
			$result['site']['values'][$site['LID']] = $site['NAME'];
		}
		// Person type
		$result['person_type'] = [
			'title' => Loc::getMessage("SP_CI_PERSON_TYPE"),
			'items' => [],
			'values' => [],
		];
		$result['person_type']['values'] = self::getSitePersonTypes($profile);
		// Payment type
		$result['pay_type'] = [
			'title' => Loc::getMessage("SP_CI_PAY_TYPE"),
			'items' => [],
			'values' => [],
		];
		$db = \CSalePaySystem::GetList(["SORT"=>"ASC", "PSA_NAME"=>"ASC"], ["ACTIVE"=>"Y"]);
		while ($item = $db->Fetch()) {
			$result['pay_type']['values'][$item['ID']] = $item['NAME'];
		}
		// Delivery type
		$result['deliv_type'] = [
			'title' => Loc::getMessage("SP_CI_DELIV_TYPE"),
			'items' => [],
			'values' => [],
		];
		$list = self::getSiteDeliveryMethods();
		foreach ($list as $item) {
			$result['deliv_type']['values'][$item['id']] = $item['name'];
		}
		// Status
		$result['status'] = [
			'title' => Loc::getMessage("SP_CI_FILTER_STATUS"),
			'items' => [],
			'values' => [],
		];
		$res = \Bitrix\Sale\Internals\StatusLangTable::getList([
			'order' => ['STATUS.SORT'=>'ASC'],
			'filter' => ['STATUS.TYPE'=>'O', 'LID'=>LANGUAGE_ID],
			'select' => ['STATUS_ID', 'NAME'],
		]);
		while ($item = $res->fetch()) {
			$result['status']['values'][$item['STATUS_ID']] = $item['NAME'];
		}
		// User group
		$result['user_group'] = [
			'title' => Loc::getMessage("SP_CI_FILTER_USER_GROUP"),
			'items' => [],
			'values' => [],
		];
		$db = \Bitrix\Main\GroupTable::getList([
			'select'  => ['NAME','ID'],
		]);
		while ($item = $db->fetch()) {
			$result['user_group']['values'][$item['ID']] = $item['NAME'];
		}
		// Order properties
		$result['prop'] = [
			'title' => Loc::getMessage("SP_CI_PROPERTIES"),
			'items' => [],
			'values' => [],
		];
		$filter = [
			'!MULTIPLE' => 'Y',
		];
		$select = ['ID', 'NAME', 'PERSON_TYPE_ID', 'TYPE', 'MULTIPLE'];
		$db = \CSaleOrderProps::GetList(["ID" => "ASC"], $filter, false, false, $select);
		while ($prop = $db->Fetch()) {
			if (!in_array($prop['TYPE'], ['TEXT', 'LOCATION', 'RADIO', 'SELECT'])) {
				continue;
			}
			$values = [];
			if (in_array($prop['TYPE'], ['RADIO', 'SELECT'])) {
				$db_values = \CSaleOrderPropsVariant::GetList(
					['SORT' => 'ASC'],
					[
						'ORDER_PROPS_ID' => $prop['ID'],
					]
				);
				while ($item = $db_values->Fetch()) {
					$values[$item['VALUE']] = $item['NAME'];
				}
			}
			$result['prop']['items'][$prop['ID']] = [
				'title' => $prop['NAME'],
				'values' => $values,
			];
		}
		return $result;
	}


	/**
	 * Fields for the contact
	 */

	public static function getSiteContactFields($profile) {
		$result = [];
		// Main user fields
		$result['user'] = [
			'title' => Loc::getMessage("SP_CI_SCF_USER_FIELDS"),
			'items' => [],
		];
		$result['user']['items'] = [
			'ID' => Loc::getMessage("SP_CI_SCF_USER_ID"),
			'LAST_NAME' => Loc::getMessage("SP_CI_SCF_LAST_NAME"),
			'NAME' => Loc::getMessage("SP_CI_SCF_NAME"),
			'SECOND_NAME' => Loc::getMessage("SP_CI_SCF_SECOND_NAME"),
			'EMAIL' => Loc::getMessage("SP_CI_SCF_EMAIL"),
			'PHONE' => Loc::getMessage("SP_CI_SCF_PHONE"),
		];
		// Order properties
		$result['props'] = [
			'title' => Loc::getMessage("SP_CI_SCF_PROPERTIES"),
			'items' => [],
		];
		$filter = [
			'!MULTIPLE' => 'Y',
		];
		$select = ['ID', 'NAME', 'PERSON_TYPE_ID', 'TYPE', 'MULTIPLE'];
		$db = \CSaleOrderProps::GetList(["ID" => "ASC"], $filter, false, false, $select);
		while ($prop = $db->Fetch()) {
			if (!in_array($prop['TYPE'], ['TEXT', 'FILE'])) {
				continue;
			}
			$result['props']['items'][$prop['PERSON_TYPE_ID']][$prop['ID']] = $prop['NAME'];
		}
		// Personal data of user
		$result['personal'] = [
			'title' => Loc::getMessage("SP_CI_SCF_USER_PERSONAL"),
			'items' => [],
		];
		$result['personal']['items'] = [
			'PERSONAL_PROFESSION' => Loc::getMessage("SP_CI_SCF_PERSONAL_PROFESSION"),
			'PERSONAL_WWW' => Loc::getMessage("SP_CI_SCF_PERSONAL_WWW"),
			'PERSONAL_ICQ' => Loc::getMessage("SP_CI_SCF_PERSONAL_ICQ"),
			'PERSONAL_GENDER' => Loc::getMessage("SP_CI_SCF_PERSONAL_GENDER"),
			'PERSONAL_BIRTHDAY' => Loc::getMessage("SP_CI_SCF_PERSONAL_BIRTHDAY"),
//			'PERSONAL_PHOTO' => Loc::getMessage("SP_CI_SCF_PERSONAL_PHOTO"),
			'PERSONAL_PHONE' => Loc::getMessage("SP_CI_SCF_PERSONAL_PHONE"),
			'PERSONAL_FAX' => Loc::getMessage("SP_CI_SCF_PERSONAL_FAX"),
			'PERSONAL_MOBILE' => Loc::getMessage("SP_CI_SCF_PERSONAL_MOBILE"),
			'PERSONAL_PAGER' => Loc::getMessage("SP_CI_SCF_PERSONAL_PAGER"),
			'PERSONAL_STREET' => Loc::getMessage("SP_CI_SCF_PERSONAL_STREET"),
			'PERSONAL_MAILBOX' => Loc::getMessage("SP_CI_SCF_PERSONAL_MAILBOX"),
			'PERSONAL_CITY' => Loc::getMessage("SP_CI_SCF_PERSONAL_CITY"),
			'PERSONAL_STATE' => Loc::getMessage("SP_CI_SCF_PERSONAL_STATE"),
			'PERSONAL_ZIP' => Loc::getMessage("SP_CI_SCF_PERSONAL_ZIP"),
//			'PERSONAL_COUNTRY' => Loc::getMessage("SP_CI_SCF_PERSONAL_COUNTRY"),
			'PERSONAL_NOTES' => Loc::getMessage("SP_CI_SCF_PERSONAL_NOTES"),
		];
		// User fields
		$result['uf'] = [
			'title' => Loc::getMessage("SP_CI_SCF_UF"),
			'items' => [],
		];
		$db = \Bitrix\Main\UserFieldTable::getList([
			'filter' => ['ENTITY_ID' => 'USER'],
			'select' => ['ID'],
		]);
		while ($item = $db->fetch()) {
			$item = \CUserTypeEntity::GetByID($item['ID']);
			$result['uf']['items'][$item['FIELD_NAME']] = $item['EDIT_FORM_LABEL'][LANGUAGE_ID];
		}
		return $result;
	}

	// Fields for the contact
	public static function getCrmContactFields($profile, $set_default=true) {
		// Fields
		$result = [
			'LAST_NAME' => [],
			'NAME' => [],
			'SECOND_NAME' => [],
			'EMAIL' => [],
			'PHONE' => [],
//			'LAST_NAME' => [
//				'name' => Loc::getMessage("SP_CI_LAST_NAME"),
//				'direction' => self::SYNC_STOC,
//				'default' => '',//LAST_NAME
//				'hint' => Loc::getMessage("SP_CI_CONTACT_LAST_NAME_HINT"),
//			],
//			'NAME' => [
//				'name' => Loc::getMessage("SP_CI_NAME"),
//				'direction' => self::SYNC_STOC,
//				'default' => '',//NAME
//				'hint' => Loc::getMessage("SP_CI_CONTACT_NAME_HINT"),
//			],
//			'SECOND_NAME' => [
//				'name' => Loc::getMessage("SP_CI_SECOND_NAME"),
//				'direction' => self::SYNC_STOC,
//				'default' => '',//SECOND_NAME
//				'hint' => Loc::getMessage("SP_CI_CONTACT_SECOND_NAME_HINT"),
//			],
//			'EMAIL' =>[
//				'name' => Loc::getMessage("SP_CI_EMAIL"),
//				'direction' => self::SYNC_STOC,
//				'default' => '',//EMAIL
//				'hint' => Loc::getMessage("SP_CI_CONTACT_EMAIL_HINT"),
//			],
//			'PHONE' => [
//				'name' => Loc::getMessage("SP_CI_PHONE"),
//				'direction' => self::SYNC_STOC,
//				'default' => '',
//				'hint' => Loc::getMessage("SP_CI_CONTACT_PHONE_HINT"),
//			],
		];
		if (self::getPreloadData('contact_fields')) {
			$list = self::getPreloadData('contact_fields');
		}
		else {
			$list = Rest::execute('crm.contact.fields');
		}
		if (!\SProdIntegration::isUtf()) {
			$list = \Bitrix\Main\Text\Encoding::convertEncoding($list, "UTF-8", "Windows-1251");
		}
		if (!empty($list)) {
			$accept_types = [
				self::DEAL_FIELDS_TYPE_LIST, self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LINK,
				self::DEAL_FIELDS_TYPE_FLOAT, self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FILE,
				self::DEAL_FIELDS_TYPE_DATETIME, self::DEAL_FIELDS_TYPE_DATE,
			];
			$exclude_fields = ['ID', 'UTM_SOURCE', 'UTM_CAMPAIGN', 'UTM_CONTENT', 'UTM_TERM', 'UTM_MEDIUM',
				'LAST_ACTIVITY_TIME', 'FACE_ID', 'ORIGIN_VERSION', 'ORIGIN_ID', 'ORIGINATOR_ID'];
			foreach ($list as $f_code => $item) {
				if ((in_array($item['type'], $accept_types) || in_array($f_code, ['EMAIL', 'PHONE']))
					&& !in_array($f_code, $exclude_fields) && !$item['isReadOnly']) {
					// Base fields
					if (strpos($f_code, 'UF_') !== 0) {
						$result[$f_code] = [
							'name'      => $item['title'],
							'direction' => self::SYNC_STOC,
							'default'   => '',
							'hint'      => Loc::getMessage("SP_CI_CONTACT_" . $f_code . "_HINT"),
						];
					}
					// User fields
					else {
						$result[$f_code] = [
							'name'      => $item['formLabel'],
							'direction' => self::SYNC_STOC,
							'default'   => '',
							'hint'      => Loc::getMessage("SP_CI_CONTACT_" . $f_code . "_HINT"),
						];
					}
				}
			}
		}
		if ($set_default) {
			// Default NAME
			$db = \CSaleOrderProps::GetList(['ID' => 'ASC'], ['IS_PAYER' => 'Y'], false, false, ['ID', 'PERSON_TYPE_ID']);
			while ($prop = $db->Fetch()) {
				$result['NAME']['default'] = is_array($result['NAME']['default']) ? $result['NAME']['default'] : [];
				$result['NAME']['default'][$prop['PERSON_TYPE_ID']] = $prop['ID'];
			}
			// Default EMAIL
			$db = \CSaleOrderProps::GetList(['ID' => 'ASC'], ['IS_EMAIL' => 'Y'], false, false, ['ID', 'PERSON_TYPE_ID']);
			while ($prop = $db->Fetch()) {
				$result['EMAIL']['default'] = is_array($result['EMAIL']['default']) ? $result['EMAIL']['default'] : [];
				$result['EMAIL']['default'][$prop['PERSON_TYPE_ID']] = $prop['ID'];
			}
			// Default PHONE
			$db = \CSaleOrderProps::GetList(['ID' => 'ASC'], ['IS_PHONE' => 'Y'], false, false, ['ID', 'PERSON_TYPE_ID']);
			while ($prop = $db->Fetch()) {
				$result['PHONE']['default'] = is_array($result['PHONE']['default']) ? $result['PHONE']['default'] : [];
				$result['PHONE']['default'][$prop['PERSON_TYPE_ID']] = $prop['ID'];
			}
		}
		return $result;
	}

	// Fields for deals
	public static function getCrmDealFields($profile, $set_default=true) {
		// Fields
		$result = [
			'TITLE' => [],
			'OPPORTUNITY' => [],
			'CURRENCY_ID' => [],
		];
		if (self::getPreloadData('deal_fields')) {
			$list = self::getPreloadData('deal_fields');
		}
		else {
			$list = Rest::execute('crm.deal.fields');
		}
		if (!\SProdIntegration::isUtf()) {
			$list = \Bitrix\Main\Text\Encoding::convertEncoding($list, "UTF-8", "Windows-1251");
		}
		if (!empty($list)) {
			$accept_types = [
				self::DEAL_FIELDS_TYPE_LIST, self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LINK,
				self::DEAL_FIELDS_TYPE_FLOAT, self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FILE,
				self::DEAL_FIELDS_TYPE_DATETIME, self::DEAL_FIELDS_TYPE_DATE,
			];
			$exclude_fields = ['ID', 'UTM_SOURCE', 'UTM_CAMPAIGN', 'UTM_CONTENT', 'UTM_TERM', 'UTM_MEDIUM',
				'LAST_ACTIVITY_TIME', 'FACE_ID', 'ORIGIN_VERSION', 'ORIGIN_ID', 'ORIGINATOR_ID'];
			foreach ($list as $f_code => $item) {
				if ((in_array($item['type'], $accept_types) || in_array($f_code, ['TITLE', 'OPPORTUNITY', 'CURRENCY_ID']))
					&& !in_array($f_code, $exclude_fields) && !$item['isReadOnly']) {
					// Base fields
					if (strpos($f_code, 'UF_CRM_') !== 0) {
						$result[$f_code] = [
							'name'      => $item['isDynamic'] ? $item['formLabel'] : $item['title'],
							'direction' => self::SYNC_STOC,
							'default'   => '',
							'hint'      => Loc::getMessage("SP_CI_DEAL_" . $f_code . "_HINT"),
						];
					}
					// User fields
					else {
						$result[$f_code] = [
							'name'      => $item['formLabel'],
							'direction' => self::SYNC_STOC,
							'default'   => '',
							'hint'      => Loc::getMessage("SP_CI_DEAL_" . $f_code . "_HINT"),
						];
					}
				}
			}
		}
		return $result;
	}

	// Fields for the contact
	public static function getCrmContactSFields($profile) {
		$result = [
			'' => Loc::getMessage("SP_CI_CCSF_PHONEMAIL"),
		];
		if (self::getPreloadData('contact_fields')) {
			$list = self::getPreloadData('contact_fields');
		}
		else {
			$list = Rest::execute('crm.contact.fields');
		}
		if (!empty($list)) {
			$accept_types = [
				self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FLOAT, self::DEAL_FIELDS_TYPE_STRING,
			];
			foreach ($list as $f_code => $item) {
				if (strpos($f_code, 'UF_') === 0 && in_array($item['type'], $accept_types)) {
					$name = $item['formLabel'];
					if (!\SProdIntegration::isUtf()) {
						$name = \Bitrix\Main\Text\Encoding::convertEncoding($name, "UTF-8", "Windows-1251");
					}
					$result[$f_code] = $name;
				}
			}
		}
		return $result;
	}

	// Fields for the company
	public static function getCrmCompanyFields($profile) {
		$presets = self::getCrmCompanyPresets($profile);
		$result = [
			'company' => [
				'title' => Loc::getMessage("SP_CI_INFO_COMPANY_COMPANY_TITLE"),
				'items' => [
					'NAME' => Loc::getMessage("SP_CI_INFO_COMPANY_COMPANY_NAME"),
					'PHONE' => Loc::getMessage("SP_CI_INFO_COMPANY_COMPANY_PHONE"),
					'EMAIL' => Loc::getMessage("SP_CI_INFO_COMPANY_COMPANY_EMAIL"),
				],
			],
			'requisite' => [
				'title' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_TITLE"),
				'items' => [
					'PRESET_ID' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_PRESET_ID"),
					'RQ_FIRST_NAME' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_FIRST_NAME"),
					'RQ_LAST_NAME' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_LAST_NAME"),
					'RQ_SECOND_NAME' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_SECOND_NAME"),
					'RQ_COMPANY_NAME' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_COMPANY_NAME"),
					'RQ_COMPANY_FULL_NAME' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_COMPANY_FULL_NAME"),
					'RQ_DIRECTOR' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_DIRECTOR"),
					'RQ_INN' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_INN"),
					'RQ_KPP' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_KPP"),
					'RQ_OGRN' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_OGRN"),
					'RQ_OGRNIP' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_OGRNIP"),
					'RQ_OKPO' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_OKPO"),
					'RQ_OKTMO' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_OKTMO"),
					'RQ_OKVED' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_OKVED"),
					'RQ_BIN' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_BIN"),
					'RQ_IIN' => Loc::getMessage("SP_CI_INFO_COMPANY_REQUISITE_RQ_IIN"),
				],
				'values' => [
					'PRESET_ID' => $presets,
				],
				'value_def' => [
					'PRESET_ID' => 1,
				],
			],
			'bankdetail' => [
				'title' => Loc::getMessage("SP_CI_INFO_COMPANY_BANKDETAIL_TITLE"),
				'items' => [
					'RQ_BANK_NAME' => Loc::getMessage("SP_CI_INFO_COMPANY_BANKDETAIL_RQ_BANK_NAME"),
					'RQ_BANK_ADDR' => Loc::getMessage("SP_CI_INFO_COMPANY_BANKDETAIL_RQ_BANK_ADDR"),
					'RQ_BIK' => Loc::getMessage("SP_CI_INFO_COMPANY_BANKDETAIL_RQ_BIK"),
					'RQ_ACC_NUM' => Loc::getMessage("SP_CI_INFO_COMPANY_BANKDETAIL_RQ_ACC_NUM"),
					'RQ_ACC_CURRENCY' => Loc::getMessage("SP_CI_INFO_COMPANY_BANKDETAIL_RQ_ACC_CURRENCY"),
					'RQ_COR_ACC_NUM' => Loc::getMessage("SP_CI_INFO_COMPANY_BANKDETAIL_RQ_COR_ACC_NUM"),
				],
			],
			'address_jur' => [
				'title' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_JUR_TITLE"),
				'items' => [
					'ADDRESS_1' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_ADDRESS_1"),
					'ADDRESS_2' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_ADDRESS_2"),
					'CITY' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_CITY"),
					'POSTAL_CODE' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_POSTAL_CODE"),
					'REGION' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_REGION"),
					'PROVINCE' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_PROVINCE"),
					'COUNTRY' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_COUNTRY"),
				],
			],
			'address_fact' => [
				'title' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_FACT_TITLE"),
				'items' => [
					'ADDRESS_1' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_ADDRESS_1"),
					'ADDRESS_2' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_ADDRESS_2"),
					'CITY' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_CITY"),
					'POSTAL_CODE' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_POSTAL_CODE"),
					'REGION' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_REGION"),
					'PROVINCE' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_PROVINCE"),
					'COUNTRY' => Loc::getMessage("SP_CI_INFO_COMPANY_ADDRESS_COUNTRY"),
				],
			],
		];
		if (self::getPreloadData('company_fields')) {
			$list = self::getPreloadData('company_fields');
		}
		else {
			$list = Rest::execute('crm.company.fields');
		}
		if (!empty($list)) {
			$accept_types = [
				self::DEAL_FIELDS_TYPE_LIST, self::DEAL_FIELDS_TYPE_STRING, self::DEAL_FIELDS_TYPE_LINK,
				self::DEAL_FIELDS_TYPE_FLOAT, self::DEAL_FIELDS_TYPE_INTEGER, self::DEAL_FIELDS_TYPE_FILE,
				self::DEAL_FIELDS_TYPE_DATETIME, self::DEAL_FIELDS_TYPE_DATE,
			];
			foreach ($list as $f_code => $item) {
				if (strpos($f_code, 'UF_') === 0 && in_array($item['type'], $accept_types)) {
					$name = $item['formLabel'];
					if (!\SProdIntegration::isUtf()) {
						$name = \Bitrix\Main\Text\Encoding::convertEncoding($name, "UTF-8", "Windows-1251");
					}
					$result['company']['items'][$f_code] = $name;
				}
			}
		}
		return $result;
	}

	// Presets of org types for the company
	public static function getCrmCompanyPresets($profile) {
		$result = [];
		if (self::getPreloadData('requisite_preset_list')) {
			$list = self::getPreloadData('requisite_preset_list');
		}
		else {
			$list = Rest::execute('crm.requisite.preset.list');
		}
		if (!empty($list)) {
			foreach ($list as $item) {
				$name = $item['NAME'];
				if (!\SProdIntegration::isUtf()) {
					$name = \Bitrix\Main\Text\Encoding::convertEncoding($name, "UTF-8", "Windows-1251");
				}
				$result[$item['ID']] = $name;
			}
		}
		return $result;
	}

	/**
	 * List of VAT variants of CRM
	 */
	public static function getCrmVatVariants() {
		$result = [];
		$list = Rest::execute('crm.vat.list');
		if (!empty($list)) {
			foreach ($list as $item) {
				$name = $item['NAME'];
				if (!\SProdIntegration::isUtf()) {
					$name = \Bitrix\Main\Text\Encoding::convertEncoding($name, "UTF-8", "Windows-1251");
				}
				$result[] = [
					'id' => $item['ID'],
					'value' => $item['RATE'],
					'name' => $name
				];
			}
		}
		return $result;
	}


	// Iblocks catalogs list
	public static function getStoreIblockList($offers=false) {
		$list = [];
		$catalog_iblocks_ids = [];
		$filter = [];
		if (!$offers) {
			$filter['PRODUCT_IBLOCK_ID'] = 0;
		}
		$catalog_iblocks = \Bitrix\Catalog\CatalogIblockTable::getList([
			'filter' => $filter,
		])->fetchAll();
		foreach ($catalog_iblocks as $catalog_iblock) {
			$catalog_iblocks_ids[] = $catalog_iblock['IBLOCK_ID'];
		}
		$res = \Bitrix\Iblock\IblockTable::getList([
			'select' => ['ID', 'NAME'],
		]);
		while ($item = $res->fetch()) {
			if (in_array($item['ID'], $catalog_iblocks_ids)) {
				$list[] = [
					'id' => $item['ID'],
					'name' => $item['NAME'],
				];
			}
		}
		return $list;
	}

	// Sections of iblock
	public static function getStoreSectionsList($iblock_id) {
		$list = [];
		if ($iblock_id) {
			$res = \Bitrix\Iblock\SectionTable::getList([
				'select' => ['ID', 'NAME', 'DEPTH_LEVEL'],
				'filter' => [
					'IBLOCK_ID' => $iblock_id,
				],
				'order'  => ['LEFT_MARGIN' => 'ASC'],
			]);
			while ($item = $res->fetch()) {
				$dots = '';
				for ($i = 0; $i < $item['DEPTH_LEVEL']; $i ++) {
					$dots .= '. ';
				}
				$list[$item['ID']] = $dots . $item['NAME'];
			}
		}
		return $list;
	}


	/**
	 * CRM products fields for storing of order ID
	 */
	public static function getCRMOrderIDFields() {
		$result = [
			'' => Loc::getMessage("SP_CI_INFO_CRM_ORDERID_FIELD_ORIGIN_ID"),
		];
		$list = Rest::execute('crm.deal.userfield.list');
		if (is_array($list) && !empty($list)) {
			$new_list = [];
			foreach ($list as $item) {
				if (in_array($item['USER_TYPE_ID'], ['integer', 'double'])) {
					$new_list[] = $item;
				}
			}
			$req_count = ceil(count($new_list) / 50);
			for ($r=0; $r<$req_count; $r++) {
				$next = $r * 50;
				$list_part = [];
				for ($j=$next; $j<$next+50 && $j<count($new_list); $j++) {
					$list_part[] = $new_list[$j];
				}
				// Get name from lang info
				$req_list = [];
				foreach ($list_part as $i => $field) {
					$req_list[$i] = [
						'method' => 'crm.deal.userfield.get',
						'params' => [
							'id' => $field['ID'],
						]
					];
				}
				$resp = Rest::batch($req_list);
				if ($resp) {
					foreach ($list_part as $i => $field) {
						$field_details = $resp[$i];
						if ( ! empty($field_details)) {
							$name = $field_details['EDIT_FORM_LABEL']['ru'];
							if (!\SProdIntegration::isUtf()) {
								$name = \Bitrix\Main\Text\Encoding::convertEncoding($name, "UTF-8", "Windows-1251");
							}
							$result[$field_details['FIELD_NAME']] = $name;
						}
					}
				}
			}
		}
		return $result;
	}

	/**
	 * CRM sources list
	 */
	public static function getCrmSources() {
		$result = [];
		if (self::getPreloadData('source_list')) {
			$list = self::getPreloadData('source_list');
		}
		else {
			$list = Rest::execute('crm.status.list', [
				'sort' => ['SORT' => 'ASC'],
				'filter' => ['ENTITY_ID' => 'SOURCE'],
			]);
		}
		if (!empty($list)) {
			foreach ($list as $item) {
				$name = $item['NAME'];
				if (!\SProdIntegration::isUtf()) {
					$name = \Bitrix\Main\Text\Encoding::convertEncoding($name, "UTF-8", "Windows-1251");
				}
				$result[] = [
					'id' => $item['STATUS_ID'],
					'name' => $name,
				];
			}
		}
		return $result;
	}

	/**
	 * Filter conditions
	 */
	public static function getNeworderConditions($profile) {
		$result = [];
		// Fields
		$result['field'] = [
			'title' => Loc::getMessage("SP_CI_INFO_NOCONDS_FIELD"),
			'items' => [],
			'values' => [],
		];
		$items = self::getCrmFields($profile);
		foreach ($items as $item) {
			$result['field']['items'][$item['id']] = [
				'title' => $item['title'],
				'values' => [],
			];
		}
		// Sources
		$result['source'] = [
			'title' => Loc::getMessage("SP_CI_INFO_NOCONDS_SOURCE"),
			'items' => [],
			'values' => [],
		];
		$items = self::getCrmSources();
		foreach ($items as $item) {
			$result['source']['values'][$item['id']] = $item['name'];
		}
		return $result;
	}


}