<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/interface/admin_lib.php");

error_reporting(E_ERROR);

global $USER;

if (!$USER->IsAuthorized()) {
	return false;
}

CModule::IncludeModule("sproduction.integration");
CModule::IncludeModule("iblock");
CModule::IncludeModule("sale");

use SProduction\Integration\FileLogControl,
	SProduction\Integration\Integration,
	SProduction\Integration\PortalHandlers,
	SProduction\Integration\PortalCustomFields,
	SProduction\Integration\StoreHandlers,
	SProduction\Integration\Settings,
	SProduction\Integration\Rest,
	SProduction\Integration\ProfilesTable,
	SProduction\Integration\FbasketProfilesTable,
	SProduction\Integration\ProfileInfo,
	SProduction\Integration\FBasketProfileInfo,
	SProduction\Integration\AddSync,
	SProduction\Integration\FBasketSyncAgent,
	SProduction\Integration\CrmProducts,
	SProduction\Integration\CrmCompany,
	SProduction\Integration\StoreProducts,
	SProduction\Integration\CheckState,
	SProduction\Integration\RemoteDiagAccess,
	SProduction\Integration\RemoteMonitorAccess,
	Bitrix\Main\Context,
    Bitrix\Main\Config\Option,
	Bitrix\Main\Localization\Loc,
	Bitrix\Currency\CurrencyManager,
    Bitrix\Sale;
use SProduction\Integration\PortalPlacements;

Loc::loadMessages(__FILE__);

$params = json_decode(file_get_contents('php://input'), true);

$action = trim($_REQUEST['action'] ?? '');
$arResult = [];
$arResult['status'] = 'error';
$arResult['errors'] = [];
$arResult['log'] = [];
$lock_result = false;

\SProdIntegration::Log('action: '.$action."\n".'params: '.print_r($params, true));

try {
	switch ($action) {

		/**
		 * Global functions
		 */

		// Main check
		case 'main_check':
			$errors = \SProdIntegration::mainCheck();
			$errors = array_merge($errors, CheckState::getErrors());
			$warnings = CheckState::getWarnings();
			$arResult['errors'] = $errors;
			$arResult['warnings'] = $warnings;
			break;

		/**
		 * Main settings
		 */

		// Data of all blocks
		case 'settings_get':
$arResult['blocks']['connect']['state'] = ['display' => true, 'active'  => false];
$arResult['blocks']['sync']['state'] = ['display' => true, 'active'  => false];
$arResult['blocks']['forgotten_basket']['state'] = ['display' => true, 'active'  => false];
$arResult['blocks']['background_sync']['state'] = ['display' => true, 'active'  => false];
$arResult['blocks']['man_fbasket_sync']['state'] = ['display' => true, 'active'  => false];
$arResult['blocks']['active']['state'] = ['display' => true, 'active'  => false];
$arResult['blocks']['profiles']['state'] = ['display' => true, 'active'  => false];
$arResult['blocks']['add_sync']['state'] = ['display' => true, 'active'  => false];
$arResult['blocks']['man_sync']['state'] = ['display' => true, 'active'  => false];
			$arResult['status'] = 'ok';
			$main_errors = \SProdIntegration::mainCheck();
			if (empty($main_errors)) {
				try {
					$app_info = Rest::getAppInfo();
					$auth_info = Rest::getAuthInfo();
					$sync_active = CheckState::isSyncActive();
				}
				catch (Exception $e) {
					$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
				}
				try {
					// Connection settings
					$site_def = SProdIntegration::getSiteDef();
					$site_def_addr = 'https://' . $site_def['SERVER_NAME'];
					$site = Settings::hasRecord("site") ? Settings::get("site") : $site_def_addr;
					$arResult['blocks']['connect']['fields'] = [
						'site'   => $site,
						'portal' => Settings::get("portal"),
						'app_id' => Settings::get("app_id"),
						'secret' => Settings::get("secret"),
					];
					if ($app_info && ! $auth_info) {
						$arResult['blocks']['connect']['fields']['auth_link'] = Rest::getAuthLink();
					}
					$arResult['blocks']['connect']['info'] = [
						'has_cred' => ($auth_info),
					];
					$arResult['blocks']['connect']['state']['active'] = true;
				}
				catch (Exception $e) {
					$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
				}
				try {
					// Synchronization params
					$direction = Settings::get("direction");
					$arResult['blocks']['sync']['fields'] = [
						'sync_mode'         => Settings::get("sync_mode"),
						'source_id'         => Settings::get("source_id"),
						'source_name'       => Settings::get("source_name"),
						'direction'         => $direction ? $direction : 'stoc',
						'start_date'        => Settings::get("start_date"),
						'crm_orderid_field' => Settings::get("crm_orderid_field"),
					];
					$arResult['blocks']['sync']['info'] = [];
					try {
						$crm_orderid_fields = ProfileInfo::getCRMOrderIDFields();
					} catch (\Exception $e) {
						$crm_orderid_fields = [];
					}
					$arResult['blocks']['sync']['info']['crm_orderid_fields'] = $crm_orderid_fields;
					$arResult['blocks']['sync']['state']['active'] = ($app_info && $auth_info);
				}
				catch (Exception $e) {
					$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
				}
				try {
					// Forgotten basket params
					$arResult['blocks']['forgotten_basket']['fields'] = [
						'fbasket_hours' => Settings::get("fbasket_hours") ?: '',
						'fbasket_deal_field' => Settings::get("fbasket_deal_field") ?: '',
						'fbasket_sync_active' => Settings::get("fbasket_sync_active") ?: '',
						'fbasket_sync_schedule' => Settings::get("fbasket_sync_schedule") ?: '',
					];
					$arResult['blocks']['forgotten_basket']['info'] = [
						'deal_string_fields' => FBasketProfileInfo::getCrmDealStringFields([]),
					];
					$arResult['blocks']['forgotten_basket']['state']['active'] = ($app_info && $auth_info && $sync_active);
				}
				catch (Exception $e) {
					$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
				}
				try {
					// Background sync settings
					$arResult['blocks']['background_sync']['fields'] = [
						'fbasket_sync_schedule' => Settings::get("fbasket_sync_schedule") ?: '',
					];
					$start_date_raw = Settings::get("start_date");
					$arResult['blocks']['background_sync']['info'] = [
						'sync_start_date' => $start_date_raw ? date('d.m.Y', strtotime($start_date_raw)) : '',
					];
					$arResult['blocks']['background_sync']['state']['active'] = ($app_info && $auth_info && $sync_active);
				}
				catch (Exception $e) {
					$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
				}
				try {
					// Manual forgotten basket synchronization
					$arResult['blocks']['man_fbasket_sync']['fields'] = [];
					$arResult['blocks']['man_fbasket_sync']['info'] = [];
					$arResult['blocks']['man_fbasket_sync']['state']['active'] = ($app_info && $auth_info && $sync_active);
				}
				catch (Exception $e) {
					$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
				}
				try {
					// Synchronization active
					$arResult['blocks']['active']['fields'] = [
						'active' => Settings::get("active"),
					];
					$arResult['blocks']['active']['state']['active'] = ($app_info && $auth_info);
				}
				catch (Exception $e) {
					$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
				}
				try {
					// Profiles warning
					$arResult['blocks']['profiles']['state']['display'] = (Settings::get("active") && ! CheckState::checkActiveProfiles());
				}
				catch (Exception $e) {
					$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
				}
				try {
					// Additional synchronization
					$arResult['blocks']['add_sync']['fields'] = [
						'add_sync_schedule' => Settings::get("add_sync_schedule"),
					];
					$arResult['blocks']['add_sync']['state']['active'] = ($app_info && $auth_info && $sync_active);
				}
				catch (Exception $e) {
					$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
				}
				try {
					// Manual synchronization
					$arResult['blocks']['man_sync']['fields'] = [
						'man_sync_period'   => Settings::get("man_sync_period"),
						'man_sync_only_new' => Settings::get("man_sync_only_new"),
					];
					$arResult['blocks']['man_sync']['state']['active'] = ($app_info && $auth_info && $sync_active);
				}
				catch (Exception $e) {
					$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
				}
				$arResult['status'] = 'ok';
			}
			break;

		// Data save
		// Connection settings (saving)
		case 'settings_connect_save':
			$fields = $params['fields'];
			$old_data = [
				'site'   => Settings::get("site"),
				'portal' => Settings::get("portal"),
				'app_id' => Settings::get("app_id"),
				'secret' => Settings::get("secret"),
			];
			$new_data = [
				'site'   => $fields["site"],
				'portal' => $fields["portal"],
				'app_id' => $fields["app_id"],
				'secret' => $fields["secret"],
			];
			// Reset placements and event handlers
			$sync_active = CheckState::isSyncActive();
			if ( ! empty(array_diff($new_data, $old_data)) && $sync_active) {
				PortalPlacements::removeAll();
				PortalCustomFields::removeAll();
				PortalHandlers::unreg();
			}
			// Save data
			if ( ! empty($fields)) {
				foreach ($fields as $name => $value) {
					Settings::save($name, $value);
				}
			}
			$arResult['status'] = 'ok';
			// Reset connection data
			if ( ! empty(array_diff($new_data, $old_data))) {
				Rest::saveAuthInfo('');
			}
			break;
		// Reset connection
		case 'settings_connect_reset':
			// Reset placements and event handlers
			$sync_active = CheckState::isSyncActive();
			if ($sync_active) {
				PortalPlacements::removeAll();
				PortalCustomFields::removeAll();
				PortalHandlers::unreg();
			}
			// Reset connection
			Rest::saveAuthInfo('');
			$arResult['status'] = 'ok';
			break;
		// Synchronization params (saving)
		case 'settings_sync_save':
			$old_data = [
				'source_id'   => Settings::get("source_id"),
				'source_name' => Settings::get("source_name"),
				'direction'   => Settings::get("direction"),
				'start_date'  => Settings::get("start_date"),
			];
			// Save data
			$fields = $params['fields'];
			if ( ! empty($fields)) {
				foreach ($fields as $name => $value) {
					Settings::save($name, $value);
				}
			}
			$arResult['status'] = 'ok';
			// Additional actions
			$new_data = [
				'source_id'   => Settings::get("source_id"),
				'source_name' => Settings::get("source_name"),
				'direction'   => Settings::get("direction"),
				'start_date'  => Settings::get("start_date"),
			];
			// Refresh placements
			if ($new_data['source_name'] != $old_data['source_name']) {
				PortalPlacements::removeAll();
				PortalCustomFields::removeAll();
				PortalPlacements::setAll();
				PortalCustomFields::setAll();
			}
			// Refresh event handlers
			if ($new_data['direction'] != $old_data['direction']) {
				PortalHandlers::unreg();
				PortalHandlers::reg();
			}
			break;
		// Forgotten basket params (saving)
		case 'settings_forgotten_basket_save':
			// Save data
			$fields = $params['fields'];
			if ( ! empty($fields)) {
				foreach ($fields as $name => $value) {
					Settings::save($name, $value);
				}
			}
			$arResult['status'] = 'ok';
			// Additional actions
			FBasketSyncAgent::set();
			break;
		case 'settings_background_sync_save':
			// Save data
			$fields = $params['fields'];
			if ( ! empty($fields)) {
				foreach ($fields as $name => $value) {
					Settings::save($name, $value);
				}
			}
			$arResult['status'] = 'ok';
			// Additional actions
			FBasketSyncAgent::set();
			break;
		// Synchronization active (saving)
		case 'settings_active_save':
			// Save data
			$fields = $params['fields'];
			if ( ! empty($fields)) {
				foreach ($fields as $name => $value) {
					Settings::save($name, $value);
				}
			}
			$arResult['status'] = 'ok';
			// Additional actions
			$sync_active = CheckState::isSyncActive();
			if ($sync_active) {
				if ( ! StoreHandlers::check()) {
					StoreHandlers::reg();
				}
				if ( ! PortalHandlers::check()) {
					PortalHandlers::reg();
				}
				PortalPlacements::setAll();
				PortalCustomFields::setAll();
			} else {
				if (StoreHandlers::check()) {
					StoreHandlers::unreg();
				}
				if (PortalHandlers::check()) {
					PortalHandlers::unreg();
				}
				PortalPlacements::removeAll();
				PortalCustomFields::removeAll();
			}
			break;
		// Additional synchronization (saving)
		case 'settings_add_sync_save':
			// Save data
			$fields = $params['fields'];
			if ( ! empty($fields)) {
				foreach ($fields as $name => $value) {
					Settings::save($name, $value);
				}
			}
			$arResult['status'] = 'ok';
			// Additional actions
			AddSync::set();
			break;
		// Manual synchronization (saving)
		case 'settings_man_sync_save':
			// Save data
			$fields = $params['fields'];
			if ( ! empty($fields)) {
				foreach ($fields as $name => $value) {
					Settings::save($name, $value);
				}
			}
			$arResult['status'] = 'ok';
			// Additional actions
			break;

//	// Connection settings (check)
//	case 'settings_connect_check':
//		$arResult['errors'] = [];
//		$arResult['status'] = 'ok';
//		break;


		/**
		 * General settings
		 */

		// Data of all blocks
		case 'general_get':
			$main_errors = \SProdIntegration::mainCheck();
			if ( ! empty($main_errors)) {
				$arResult['blocks']['main']['state']['display'] = false;
				$arResult['blocks']['products']['state']['display'] = false;
				$arResult['blocks']['prodsync']['state']['display'] = false;
				$arResult['blocks']['prodsearch']['state']['display'] = false;
				$arResult['status'] = 'ok';
			} else {
				$app_info = Rest::getAppInfo();
				$auth_info = Rest::getAuthInfo();
				$products_sync_type = Settings::get("products_sync_type");
				// General data
				$store_iblocks = ProfileInfo::getStoreIblockList(true);
				$crm_iblocks = [];
				if ($products_sync_type == 'find_new') {
					$crm_iblocks = ProfileInfo::getCrmIblockList();
				}
				// Main params
				$arResult['blocks']['main']['state'] = [
					'display' => true,
					'active'  => ($app_info && $auth_info),
				];
				$arResult['blocks']['main']['fields'] = [
					'contacts_sync_mode'          => Settings::get("contacts_sync_mode"),
					'contacts_phonemail_mode'     => Settings::get("contacts_phonemail_mode"),
					'contacts_link_mode'          => Settings::get("contacts_link_mode"),
					'cancel_pays_by_cancel_order' => Settings::get("cancel_pays_by_cancel_order"),
					'link_responsibles'           => Settings::get("link_responsibles"),
					'notif_errors'                => Settings::get("notif_errors"),
					'crm_orderid_field'           => Settings::get("crm_orderid_field"),
				];
				$arResult['blocks']['main']['info'] = [];
				$arResult['blocks']['main']['info']['crm_orderid_fields'] = ProfileInfo::getCRMOrderIDFields();
				// Product list params
				if (Settings::getSyncMode() == Settings::SYNC_MODE_BOX) {
					$arResult['blocks']['products']['state'] = [
						'display' => true,
						'active'  => false,
					];
				}
				else {
					$arResult['blocks']['products']['state'] = [
						'display' => true,
						'active'  => ($app_info && $auth_info),
					];
					$arResult['blocks']['products']['fields'] = [
						'products_no_discounts'     => Settings::get("products_no_discounts"),
						'products_discounts_perc'   => Settings::get("products_discounts_perc"),
						'products_name_props'       => Settings::get("products_name_props", true),
						'products_name_props_delim' => Settings::get("products_name_props_delim"),
						'products_complects'        => Settings::get("products_complects"),
						'products_delivery'         => Settings::get("products_delivery"),
						'products_deliv_prod_active' => Settings::get("products_deliv_prod_active"),
						'products_deliv_prod_ttlprod' => Settings::get("products_deliv_prod_ttlprod"),
						'products_deliv_prod_list'  => Settings::get("products_deliv_prod_list", true),
						'products_delivery_vat'     => Settings::get("products_delivery_vat"),
						'products_delivery_vat_included'     => Settings::get("products_delivery_vat_included"),
						'products_sync_type'        => $products_sync_type,
						'products_root_section'     => Settings::get("products_root_section"),
						'products_group_by_orders'  => Settings::get("products_group_by_orders"),
						'products_iblock'           => Settings::get("products_iblock"),
						'products_mixed_update_lock' => Settings::get("products_mixed_update_lock"),
						'products_sync_allow_delete' => Settings::get("products_sync_allow_delete"),
					];
					$arResult['blocks']['products']['info'] = [
						'sections' => CrmProducts::getSectHierarchy(),
						'iblocks'  => $store_iblocks,
						'delivery_list'  => ProfileInfo::getSiteDeliveryMethods(),
						'crm_vat_list'  => ProfileInfo::getCrmVatVariants(),
					];
				}
				// Params of products synchronization
				if (Settings::getSyncMode() == Settings::SYNC_MODE_BOX) {
					$arResult['blocks']['prodsync']['state'] = [
						'display' => true,
						'active'  => false,
					];
				}
				else {
					$products_iblock = (int) Settings::get("products_iblock");
					$arResult['blocks']['prodsync']['state'] = [
						'display' => true,
						'active'  => ($app_info && $auth_info &&
							($products_sync_type == 'create' || $products_sync_type == 'mixed') &&
							$products_iblock),
					];
					$arResult['blocks']['prodsync']['fields'] = [
						'products_comp_table' => Settings::get("products_comp_table", true),
					];
					$arResult['blocks']['prodsync']['info'] = [
						'products_iblock'   => $products_iblock,
						'store_prod_fields' => false,
						'crm_prod_fields'   => false,
					];
					if ($products_iblock) {
						$arResult['blocks']['prodsync']['info']['store_prod_fields'] = StoreProducts::getStoreFields($products_iblock);
						$arResult['blocks']['prodsync']['info']['crm_prod_fields'] = CrmProducts::getCRMFields();
					}
				}
				// Params of products search
				if (Settings::getSyncMode() == Settings::SYNC_MODE_BOX) {
					$arResult['blocks']['prodsearch']['state'] = [
						'display' => true,
						'active'  => false,
					];
				}
				else {
					$products_iblock = (int) Settings::get("products_iblock");
					$arResult['blocks']['prodsearch']['state'] = [
						'display' => true,
						'active'  => ($app_info && $auth_info &&
							($products_sync_type == 'find' || $products_sync_type == 'mixed' || $products_sync_type == 'find_new')
						),
					];
					$arResult['blocks']['prodsearch']['fields'] = [
						'products_search_store_fields' => StoreProducts::getSearchFields(),
						'products_search_crm_fields'   => CrmProducts::getSearchFields(),
						'products_search_crm_field'    => Settings::get("products_search_crm_field"),
					];
					$arResult['blocks']['prodsearch']['info'] = [
						'products_iblock'   => $products_iblock,
						'store_prod_fields' => false,
						'crm_prod_fields'   => false,
						'store_iblocks'     => $store_iblocks,
						'crm_iblocks'       => $crm_iblocks,
						'products_sync_type'  => $products_sync_type,
					];
					$info_store_prod_fields = [];
					foreach ($store_iblocks as $iblock) {
						$excludes = [];
						if ($products_sync_type == 'find_new') {
							$excludes = ['ID'];
						}
						$info_store_prod_fields[$iblock['id']] = StoreProducts::getStoreFieldsForID($iblock['id'], $excludes);
					}
					$arResult['blocks']['prodsearch']['info']['store_prod_fields'] = $info_store_prod_fields;
					try {
						$crm_prod_fields = CrmProducts::getCRMFieldsForID();
					} catch (\Exception $e) {
						$crm_prod_fields = [];
					}
					$arResult['blocks']['prodsearch']['info']['crm_prod_fields'] = $crm_prod_fields;
					if (!empty($crm_iblocks)) {
						$info_crm_prod_fields = [];
						foreach ($crm_iblocks as $iblock) {
							$info_crm_prod_fields[$iblock['id']] = CrmProducts::getCRMFieldsForIDNew($iblock['id']);
						}
					}
					$arResult['blocks']['prodsearch']['info']['crm_prod_fields_new'] = $info_crm_prod_fields;
				}
				$arResult['errors'] = [];
				$arResult['status'] = 'ok';
			}
			break;

		// Data save
		// Main params (saving)
		case 'general_main_save':
			// Product list params (saving)
		case 'general_products_save':
			// Products sync params (saving)
		case 'general_prodsync_save':
			// Save data
			$fields = $params['fields'];
			if ( ! empty($fields)) {
				foreach ($fields as $name => $value) {
					if ($name == 'products_name_props' ||
						$name == 'products_comp_table' ||
						$name == 'products_deliv_prod_list') {
						$value = serialize($value);
					}
					Settings::save($name, $value);
				}
			}
			$arResult['status'] = 'ok';
			// Additional actions
			break;
		// Products search params (saving)
		case 'general_prodsearch_save':
			// Save data
			$fields = $params['fields'];
			if ( ! empty($fields)) {
				foreach ($fields['products_search_store_fields'] as $iblock => $field) {
					StoreProducts::setSearchFields($iblock, $field);
				}
				foreach ($fields['products_search_crm_fields'] as $iblock => $field) {
					CrmProducts::setSearchFields($iblock, $field);
				}
				Settings::save('products_search_crm_field', $fields['products_search_crm_field']);
			}
			$arResult['status'] = 'ok';
			// Additional actions
			break;


		/**
		 * Profiles list
		 */

		// Profiles list
		case 'profiles_list':
			$main_errors = \SProdIntegration::mainCheck();
			if ( ! empty($main_errors)) {
				$list = [];
			} else {
				$list = ProfilesTable::getList([
					'order'  => ['id' => 'asc'],
					'select' => ['id', 'sort', 'name', 'active'],
				]);
			}
			if ( ! \SProdIntegration::isUtf()) {
				$list = \Bitrix\Main\Text\Encoding::convertEncoding($list, "UTF-8", "Windows-1251");
			}
			$arResult['list'] = $list;
			$arResult['status'] = 'ok';
			break;

		// Profile creation
		case 'profiles_add':
			$profile_name = Loc::getMessage("SP_CI_NEW_PROFILE");
			if ( ! \SProdIntegration::isUtf()) {
				$profile_name = \Bitrix\Main\Text\Encoding::convertEncoding($profile_name, "Windows-1251", "UTF-8");
			}
			$result = ProfilesTable::add([
				'name'   => $profile_name,
				'active' => 'N',
			]);
			if ($result->isSuccess()) {
				$id = $result->getId();
				$arResult['id'] = $id;
				$arResult['status'] = 'ok';
			} else {
				$errors = $result->getErrorMessages();
				$arResult['errors'] = $result->getErrorMessages();
			}
			break;

		/**
		 * Forgotten basket profiles list
		 */
		case 'fbasket_profiles_list':
			$main_errors = \SProdIntegration::mainCheck();
			if ( ! empty($main_errors)) {
				$list = [];
			} else {
				$list = FbasketProfilesTable::getList([
					'order'  => ['id' => 'asc'],
					'select' => ['id', 'name', 'active'],
				]);
			}
			if ( ! \SProdIntegration::isUtf()) {
				$list = \Bitrix\Main\Text\Encoding::convertEncoding($list, "UTF-8", "Windows-1251");
			}
			$arResult['list'] = $list;
			$arResult['status'] = 'ok';
			break;

		// Forgotten basket profile creation
		case 'fbasket_profiles_add':
			$profile_name = Loc::getMessage("SP_CI_NEW_PROFILE");
			if ( ! \SProdIntegration::isUtf()) {
				$profile_name = \Bitrix\Main\Text\Encoding::convertEncoding($profile_name, "Windows-1251", "UTF-8");
			}
			$result = FbasketProfilesTable::add([
				'name'     => $profile_name,
				'active'   => 'N',
				'options'  => serialize([]),
				'contacts' => serialize([]),
				'fields'   => serialize([]),
				'statuses' => serialize([]),
			]);
			if ($result->isSuccess()) {
				$id = $result->getId();
				$arResult['id'] = $id;
				$arResult['status'] = 'ok';
			} else {
				$arResult['errors'] = $result->getErrorMessages();
			}
			break;

		// Forgotten basket profile info
		case 'fbasket_profile_info':
			$id = $params['id'];
			$main_errors = \SProdIntegration::mainCheck();
			if ( ! empty($main_errors)) {
				$info = [];
			} else {
				$profile = FbasketProfilesTable::getById($id);
				if ( ! is_array($profile)) {
					$profile = [];
				}
				$profile['options'] = (isset($profile['options']) && is_array($profile['options'])) ? $profile['options'] : [];
				FBasketProfileInfo::preloadCrmData($profile);
				$info = [
					'crm' => [
						'users' => FBasketProfileInfo::getCrmUsers($profile),
						'directions' => FBasketProfileInfo::getCrmDirections($profile),
						'sources' => FBasketProfileInfo::getCrmSources(),
						'stages' => FBasketProfileInfo::getCrmStages($profile),
						'contact_fields' => FBasketProfileInfo::getCrmContactFields($profile),
						'contact_search_fields' => FBasketProfileInfo::getCrmContactSFields($profile),
						'deal_fields' => FBasketProfileInfo::getCrmDealFields($profile),
					],
					'site' => [
						'sites' => \SProduction\Integration\ProfileInfo::getSitesList(),
						'contact_fields' => FBasketProfileInfo::getSiteContactFields($profile),
						'basket_fields' => FBasketProfileInfo::getSiteBasketFields($profile),
					],
				];
			}
			$arResult['info'] = $info;
			$arResult['status'] = 'ok';
			break;

		// Forgotten basket profile data
		case 'fbasket_profile_get':
			$id = $params['id'];
			$main_errors = \SProdIntegration::mainCheck();
			if ( ! empty($main_errors)) {
				$profile = [];
			} else {
				$profile = FbasketProfilesTable::getById($id);
			}
			if ( ! is_array($profile)) {
				$profile = [];
			}
			$arResult['blocks'] = [
				'main' => [
					'active' => $profile['active'] ?? 'N',
					'name' => $profile['name'] ?? '',
					'site' => $profile['site'] ?? '',
					'options' => (isset($profile['options']) && is_array($profile['options'])) ? $profile['options'] : [],
				],
				'contacts' => (isset($profile['contacts']) && is_array($profile['contacts'])) ? $profile['contacts'] : [],
				'fields' => (isset($profile['fields']) && is_array($profile['fields'])) ? $profile['fields'] : [],
				'statuses' => (isset($profile['statuses']) && is_array($profile['statuses'])) ? $profile['statuses'] : [],
			];
			$arResult['status'] = 'ok';
			break;

		// Forgotten basket profile update
		case 'fbasket_profile_save':
			$id = $params['id'];
			$block_code = $params['block'];
			$inp_fields = $params['fields'] ?? [];
			$fields = [];
			if ($block_code == 'main') {
				if (array_key_exists('active', $inp_fields)) {
					$fields['active'] = $inp_fields['active'];
				}
				if (array_key_exists('name', $inp_fields)) {
					$fields['name'] = $inp_fields['name'];
				}
				if (array_key_exists('site', $inp_fields)) {
					$fields['site'] = $inp_fields['site'];
				}
				if (array_key_exists('options', $inp_fields)) {
					$fields['options'] = is_array($inp_fields['options']) ? $inp_fields['options'] : [];
				}
			}
			elseif ($block_code == 'contacts') {
				$fields['contacts'] = $inp_fields;
			}
			elseif ($block_code == 'fields') {
				$fields['fields'] = $inp_fields;
			}
			elseif ($block_code == 'statuses') {
				$fields['statuses'] = $inp_fields;
			}
			$result = FbasketProfilesTable::update($id, $fields);
			if ($result->isSuccess()) {
				$arResult['status'] = 'ok';
			} else {
				$arResult['errors'] = $result->getErrorMessages();
			}
			break;

		// Forgotten basket profile delete
		case 'fbasket_profile_del':
			$id = $params['id'];
			FbasketProfilesTable::delete($id);
			$arResult['status'] = 'ok';
			break;

		/**
		 * Profile edit
		 */

		// Information for profile viewing
		case 'profile_info':
			$id = $params['id'];
			$main_errors = \SProdIntegration::mainCheck();
			if ( ! empty($main_errors)) {
				$list = [];
			} else {
				$info = ProfileInfo::getAll($id);
			}
			if ($info) {
				$arResult['info'] = $info;
			}
			$arResult['status'] = 'ok';
			break;

		// Profile data
		case 'profile_get':
			$id = $params['id'];
			$main_errors = \SProdIntegration::mainCheck();
			if ( ! empty($main_errors)) {
				$list = [];
			} else {
				$profile = ProfilesTable::getById($id);
			}
			// Additionsl params
			$params = [
				'main' => [],
				'filter' => [],
				'contact' => [],
				'statuses' => [],
				'props' => [],
				'other' => [],
				'neworder' => [],
			];
			// Data
			if ($profile) {
				// Main tab
				$profile['main'] = $profile['options'];
				if ( ! $profile['main']) {
					$prefix = Loc::getMessage("SP_CI_PROFILE_PREFIX_DEFAULT");
					if ( ! \SProdIntegration::isUtf()) {
						$prefix = \Bitrix\Main\Text\Encoding::convertEncoding($prefix, "Windows-1251", "UTF-8");
					}
					$profile['main']['prefix'] = $prefix;
				}
				$profile['main']['active'] = $profile['active'];
				$profile['main']['name'] = $profile['name'];
				$profile['main']['deal_category'] = $profile['main']['deal_category'] ? : 0;
				// Statuses tab
				// TODO: Filter values not shown in profile information
				$info = ProfileInfo::getAll($id);
				$new_list = [];
				$stage_list = [];
				foreach ($info['crm']['stages'] as $stage) {
					$stage_list[] = $stage['id'];
				}
				foreach ($profile['statuses']['cancel_stages'] as $k => $stage_id) {
					if (in_array($stage_id, $stage_list)) {
						$new_list[] = $stage_id;
					}
				}
				$profile['statuses']['cancel_stages'] = $new_list;
				if ( ! \SProdIntegration::isUtf()) {
					$profile = \Bitrix\Main\Text\Encoding::convertEncoding($profile, "UTF-8", "Windows-1251");
				}
				// Summary
				$arResult['blocks'] = $profile;
				// Category ID for new order filter info
				$params['neworder']['condition_category_id'] = $profile['main']['deal_category'];
				// Terms for enabled tab
				$params['neworder']['enabled'] = false;
				if (Settings::getSyncMode() != Settings::SYNC_MODE_NORMAL) {
					$params['neworder']['warnings'][] = [
						'message' => Loc::getMessage("SP_CI_GET_PROFILE_NEWORDER_WARN_SYNCMODE"),
						'hint' => Loc::getMessage("SP_CI_GET_PROFILE_NEWORDER_WARN_SYNCMODE_HINT")
					];
				}
				elseif (Settings::get("direction") != 'full') {
					$params['neworder']['warnings'][] = [
						'message' => Loc::getMessage("SP_CI_GET_PROFILE_NEWORDER_WARN_DIRECTION"),
						'hint' => Loc::getMessage("SP_CI_GET_PROFILE_NEWORDER_WARN_DIRECTION_HINT")
					];
				}
//				elseif (Settings::get("products_sync_type") != 'find_new') {
//					$params['neworder']['warnings'][] = [
//						'message' => Loc::getMessage("SP_CI_GET_PROFILE_NEWORDER_WARN_PRODSYNCTYPE"),
//						'hint' => Loc::getMessage("SP_CI_GET_PROFILE_NEWORDER_WARN_PRODSYNCTYPE_HINT")
//					];
//				}
				else {
					$params['neworder']['enabled'] = true;
				}
				// Other warnings
				if (Settings::getSyncMode() == Settings::SYNC_MODE_NORMAL && Settings::get("products_sync_type") != 'find_new') {
					$params['neworder']['warnings'][] = [
						'message' => Loc::getMessage("SP_CI_GET_PROFILE_NEWORDER_WARN_PRODSYNCTYPE"),
						'hint' => Loc::getMessage("SP_CI_GET_PROFILE_NEWORDER_WARN_PRODSYNCTYPE_HINT")
					];
				}
			}
			$arResult['params'] = $params;
			$arResult['status'] = 'ok';
			break;

		// Profile update
		case 'profile_save':
			$id = $params['id'];
			$block_code = $params['block'];
			$fields = [];
			$inp_fields = $params['fields'];
			\SProdIntegration::Log('save fields ' . print_r($inp_fields, 1));
			if ( ! empty($inp_fields)) {
				foreach ($inp_fields as $name => $value) {
					//TODO
					if ($block_code == 'main') {
						switch ($name) {
							case 'active':
							case 'name':
								$fields[$name] = $value;
								break;
							default:
								$fields['options'][$name] = $value;
						}
					} else {
						$fields[$block_code] = $inp_fields;
					}
				}
			}
			$result = ProfilesTable::update($id, $fields);
			if ($result->isSuccess()) {
				$arResult['status'] = 'ok';
			} else {
				$errors = $result->getErrorMessages();
				$arResult['errors'] = $result->getErrorMessages();
			}
			break;

		// Profile delete
		case 'profile_del':
			$id = $params['id'];
			ProfilesTable::delete($id);
			$arResult['status'] = 'ok';
			break;


		/**
		 * State of system
		 */

		// All data blocks
		case 'status_get':
			$arResult['blocks']['table']['state']['display'] = false;
			$arResult['blocks']['table']['filelog']['display'] = false;
			$arResult['blocks']['table']['remote']['display'] = false;
			$arResult['blocks']['table']['monitor']['display'] = false;
			try {
				$arResult['blocks']['table']['fields'] = CheckState::checkList();
				$arResult['blocks']['table']['state'] = [
					'display' => true,
					'active'  => true,
				];
			}
			catch (Exception $e) {
				$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
			}
			try {
				$arResult['blocks']['filelog']['fields']['active'] = (Settings::get("filelog") == 'Y');
				$arResult['blocks']['filelog']['fields']['link'] = FileLogControl::getLink();
				$arResult['blocks']['filelog']['fields']['info'] = FileLogControl::get();
				$arResult['blocks']['filelog']['state'] = [
					'display' => true,
					'active'  => true,
				];
			}
			catch (Exception $e) {
				$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
			}
			try {
				$arResult['blocks']['remote']['fields']['active'] = RemoteDiagAccess::isActive();
				$arResult['blocks']['remote']['fields']['link'] = RemoteDiagAccess::getLink();
				$arResult['blocks']['remote']['fields']['link_state'] = RemoteDiagAccess::getState();
				$arResult['blocks']['remote']['fields']['close_date'] = RemoteDiagAccess::getTime();
				$arResult['blocks']['remote']['fields']['server_time'] = date('d.m.Y H:i');
				$arResult['blocks']['remote']['state'] = [
					'display' => true,
					'active'  => true,
				];
			}
			catch (Exception $e) {
				$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
			}
			try {
				$arResult['blocks']['monitor']['fields']['active'] = RemoteMonitorAccess::isActive();
				$arResult['blocks']['monitor']['fields']['code'] = RemoteMonitorAccess::getAccessKey();
				$arResult['blocks']['monitor']['fields']['link'] = RemoteMonitorAccess::getLink();
				$arResult['blocks']['monitor']['state'] = [
					'display' => true,
					'active'  => true,
				];
			}
			catch (Exception $e) {
				$arResult['errors'][] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
			}
			$arResult['status'] = 'ok';
			break;

		case 'status_filelog_reset':
			FileLogControl::reset();
			$arResult['status'] = 'ok';
			break;

		case 'status_monitor_refresh':
			RemoteMonitorAccess::generateAccessKey();
			$arResult['status'] = 'ok';
			break;

		// Table of system state
		case 'status_table_save':
			$arResult['status'] = 'ok';
			break;

		// File log
		case 'status_filelog_save':
			$fields = $params['fields'];
			FileLogControl::changeStatus($fields['active']);
			$arResult['status'] = 'ok';
			break;

		// Remote monitor
		case 'status_monitor_save':
			$fields = $params['fields'];
			RemoteMonitorAccess::setActive((bool)$fields['active']);
			$arResult['status'] = 'ok';
			break;

		// File log
		case 'status_remote_save':
			$fields = $params['fields'];
			RemoteDiagAccess::setTime($fields['close_date']);
			RemoteDiagAccess::setActive($fields['active']);
			$arResult['status'] = 'ok';
			break;


		/**
		 * Products edit
		 */

		// Products list
		case 'products_edit_items_list':
			$main_errors = \SProdIntegration::mainCheck();
			$site = Settings::get("site");
			$list = [];
			$count = 0;
			if (empty($main_errors)) {
				$filter = [
					'iblock' => $params['filter']['iblock'],
					'name' => $params['filter']['name'],
					'section' => $params['filter']['section'],
				];
				$page = (int) $params['page'];
				$fields_sel = StoreProducts::getIblockFieldsSelected($filter['iblock']);
				$fields_list = StoreProducts::getIblockFieldsList($filter['iblock'], $fields_sel);
				$fields_all = StoreProducts::getIblockFieldsList($filter['iblock']);
				$count = StoreProducts::getParentProds($filter, [], $fields_sel, true);
				$list = StoreProducts::getParentProds($filter, [], $fields_sel, false, 10, $page);
			}
			$arResult['list'] = $list;
			$arResult['count'] = $count;
			$arResult['fields_sel'] = $fields_sel;
			$arResult['fields_list'] = $fields_list;
			$arResult['fields_all'] = $fields_all;
			$arResult['status'] = 'ok';
			break;

		// Sku list
		case 'products_edit_sku_list':
			$main_errors = \SProdIntegration::mainCheck();
			$site = Settings::get("site");
			$fields_sel = [];
			$fields_list = [];
			$fields_all = [];
			$list = [];
			$sku_iblock_id = false;
			if (empty($main_errors)) {
				$iblock_id = (int)$params['iblock'];
				$product_id = (int)$params['product_id'];
				if (!$iblock_id) {
					$element = StoreProducts::getProduct($product_id);
					$iblock_id = $element['IBLOCK_ID'];
				}
				$catalog_info = \CCatalogSKU::GetInfoByProductIBlock($iblock_id);
				if ($catalog_info) {
					$sku_iblock_id = $catalog_info['IBLOCK_ID'];
				}
				if ($sku_iblock_id) {
					$fields_sel = StoreProducts::getIblockFieldsSelected($sku_iblock_id);
					$fields_list = StoreProducts::getIblockFieldsList($sku_iblock_id, $fields_sel);
					$fields_all = StoreProducts::getIblockFieldsList($sku_iblock_id);
				}
				$list = StoreProducts::getSkuProds($iblock_id, $params['product_id'], false, $fields_sel);
			}
			$arResult['list'] = $list;
			$arResult['iblock_id'] = $sku_iblock_id;
			$arResult['fields_sel'] = $fields_sel;
			$arResult['fields_list'] = $fields_list;
			$arResult['fields_all'] = $fields_all;
			$arResult['status'] = 'ok';
			break;

		// Get filter data
		case 'products_edit_filter_data':
			$main_errors = \SProdIntegration::mainCheck();
			$iblocks = [];
			$sections = [];
			if (empty($main_errors)) {
				// Iblocks
				$iblocks = ProfileInfo::getStoreIblockList();
				// Sections
				if ($params['iblock']) {
					$sections = ProfileInfo::getStoreSectionsList($params['iblock']);
				}
			}
			$arResult['iblocks'] = $iblocks;
			$arResult['sections'] = $sections;
			$arResult['status'] = 'ok';
			break;

		// Save fields list
		case 'products_edit_save_fields':
			\SProduction\Integration\StoreProducts::setIblockFieldsSelected($params['iblock'], $params['fields']);
			$arResult['status'] = 'ok';
			break;

		// Add product to order
		case 'products_edit_add_item':
			$order_id = (int) $params['order_id'];
			$item_id = (int) $params['item_id'];
			if ($order_id && $item_id) {
				if ($order = Sale\Order::load($order_id)) {
					$basket = $order->getBasket();
					// If product exist
					$product_exist = false;
					foreach ($basket as $item) {
						if ($item->getProductId() == $item_id) {
							// Change quantity
							$item->setField('QUANTITY', $item->getQuantity() + 1);
							$refreshStrategy = \Bitrix\Sale\Basket\RefreshFactory::create(\Bitrix\Sale\Basket\RefreshFactory::TYPE_FULL);
							$basket->refresh($refreshStrategy);
							$res = $order->save();
							if ( ! $res->isSuccess()) {
								\SProdIntegration::Log('order ' . $order_id . ' add quantity for product ' . $item_id . ' error "' . $res->getErrorMessages() . '"');
							} else {
								\SProdIntegration::Log('order ' . $order_id . ' add quantity for product ' . $item_id . ' success');
							}
							$product_exist = true;
							$arResult['status'] = 'ok';
							break;
						}
					}
					// If new product
					if ( ! $product_exist) {
						$currency_code = CurrencyManager::getBaseCurrency();
						if ($product = \Bitrix\Iblock\ElementTable::getById($item_id)->fetch()) {
							$item = $basket->createItem('catalog', $item_id);
							$product_name = $product['NAME'];
							$measure_info = \Bitrix\Catalog\ProductTable::getCurrentRatioWithMeasure([$item_id]);
							$measure = $measure_info[$item_id]['MEASURE'];
							$item->setFields([
								'QUANTITY'               => 1,
								'CURRENCY'               => $currency_code,
								'PRODUCT_PROVIDER_CLASS' => \Bitrix\Catalog\Product\Basket::getDefaultProviderName(),
								'MEASURE_CODE'           => $measure['CODE'],
								'MEASURE_NAME'           => $measure['SYMBOL'],
								'NAME'                   => $product_name,
							]);
							$res = $order->save();
							if ( ! $res->isSuccess()) {
								\SProdIntegration::Log('order ' . $order_id . ' add product ' . $item_id . ' error "' . $res->getErrorMessages() . '"');
							} else {
								\SProdIntegration::Log('order ' . $order_id . ' add product ' . $item_id . ' success');
							}
							$arResult['status'] = 'ok';
						}
					}
				}
			}
			break;

		// Cart list
		case 'products_edit_cart_list':
			$main_errors = \SProdIntegration::mainCheck();
			$list = [];
			if (empty($main_errors)) {
				if ( ! \SProdIntegration::isUtf()) {
					$list = \Bitrix\Main\Text\Encoding::convertEncoding($list, "UTF-8", "Windows-1251");
				}
			}
			$arResult['list'] = $list;
			$arResult['status'] = 'ok';
			break;


		/**
		 * Other functions
		 */

		case 'otherfunc_find_user':
			$s_string = $params['search'];
			$user_list = \SProduction\Integration\StoreUser::findUsers($s_string, 15);
			foreach ($user_list as $item) {
				$list[] = [
					'code' => $item['id'],
					'label' => $item['name'] ? ($item['name'] . ' (' . $item['email'] . ')') : $item['email']
				];
			}
			$arResult['list'] = $list;
			$arResult['status'] = 'ok';
			break;


		/**
		 * testing
		 */

		case 'test':
			$arResult['data'] = 123;
			$arResult['status'] = 'ok';
			break;

	}
} catch (Exception $e) {
	$arResult['status'] = 'error';
	$arResult['error'] = \SProdIntegration::formatError($e->getCode(), $e->getMessage());
}

if (!$lock_result) {
	echo \Bitrix\Main\Web\Json::encode($arResult);
}
