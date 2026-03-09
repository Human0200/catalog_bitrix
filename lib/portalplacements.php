<?php
/**
 * Event handlers of portal
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
	Bitrix\Sale,
	SProdIntegration;

Loc::loadMessages(__FILE__);

class PortalPlacements {
	const MODULE_ID = 'sproduction.integration';
	public const HANDLER_URL = '/bitrix/sprod_integr_admin.php';

	public static function setAll() {
		if ( ! Rest::checkConnection()) {
			return;
		}
		$app_info = Rest::getAppInfo();
		$source_name = Settings::get('source_name');
		$list = Rest::execute('placement.get');
		// Order edit button
		$script_url = $app_info['site'] . self::HANDLER_URL;
		$check = false;
		foreach ($list as $placement) {
			if ($placement['placement'] == 'CRM_DEAL_DETAIL_TOOLBAR' && $placement['handler'] == $script_url) {
				$check = true;
			}
		}
		if ( ! $check) {
			$title = Loc::getMessage("SP_CI_ORDER_EDIT") . ($source_name ? (' (' . $source_name . ')') : '');
			if ( ! SProdIntegration::isUtf()) {
				$title = \Bitrix\Main\Text\Encoding::convertEncoding($title, "Windows-1251", "UTF-8");
			}
			self::setItem('CRM_DEAL_DETAIL_TOOLBAR', $script_url, $title);
		}
		// Order create button
		$script_url = $app_info['site'] . self::HANDLER_URL;
		$check = false;
		foreach ($list as $placement) {
			if ($placement['placement'] == 'CRM_DEAL_LIST_TOOLBAR' && $placement['handler'] == $script_url) {
				$check = true;
			}
		}
		if ( ! $check) {
			$title = Loc::getMessage("SP_CI_ORDER_CREATE") . ($source_name ? (' (' . $source_name . ')') : '');
			if ( ! SProdIntegration::isUtf()) {
				$title = \Bitrix\Main\Text\Encoding::convertEncoding($title, "Windows-1251", "UTF-8");
			}
			self::setItem('CRM_DEAL_LIST_TOOLBAR', $script_url, $title);
		}
		// Products edit button
		$script_url = $app_info['site'] . self::HANDLER_URL;
		$check = false;
		foreach ($list as $placement) {
			if ($placement['placement'] == 'CRM_DEAL_DETAIL_TAB' && $placement['handler'] == $script_url) {
				$check = true;
			}
		}
		if ( ! $check) {
			$title = Loc::getMessage("SP_CI_PRODUCTS_EDIT") . ($source_name ? (' (' . $source_name . ')') : '');
			if ( ! SProdIntegration::isUtf()) {
				$title = \Bitrix\Main\Text\Encoding::convertEncoding($title, "Windows-1251", "UTF-8");
			}
			self::setItem('CRM_DEAL_DETAIL_TAB', $script_url, $title);
		}
	}

	public static function removeAll() {
		if ( ! Rest::checkConnection()) {
			return;
		}
		$app_info = Rest::getAppInfo();
		try {
			$list = Rest::execute('placement.get');
		} catch (\Exception $e) {
		}
		// Order edit button
		$script_url = $app_info['site'] . '/bitrix/admin/sprod_integr_order_edit.php'; //< Old link
		self::removeItem($list, $script_url, 'CRM_DEAL_DETAIL_TOOLBAR');
		$script_url = $app_info['site'] . self::HANDLER_URL;
		self::removeItem($list, $script_url, 'CRM_DEAL_DETAIL_TOOLBAR');
		// Order create button
		$script_url = $app_info['site'] . '/bitrix/admin/sprod_integr_order_create.php'; //< Old link
		self::removeItem($list, $script_url, 'CRM_DEAL_LIST_TOOLBAR');
		$script_url = $app_info['site'] . self::HANDLER_URL;
		self::removeItem($list, $script_url, 'CRM_DEAL_LIST_TOOLBAR');
		// Products edit button
		$script_url = $app_info['site'] . '/bitrix/admin/sprod_integr_products_edit.php'; //< Old link
		self::removeItem($list, $script_url, 'CRM_DEAL_DETAIL_TAB');
		$script_url = $app_info['site'] . self::HANDLER_URL;
		self::removeItem($list, $script_url, 'CRM_DEAL_DETAIL_TAB');
	}

	public static function setItem($code, $script_url, $title) {
		try {
			Rest::execute('placement.bind', [
				'PLACEMENT' => $code,
				'HANDLER'   => $script_url,
				'TITLE'     => $title,
			]);
		} catch (\Exception $e) {
		}
	}

	public static function removeItem($list, $script_url, $code) {
		$check = false;
		foreach ($list as $placement) {
			if ($placement['placement'] == $code && $placement['handler'] == $script_url) {
				$check = true;
			}
		}
		if ($check) {
			try {
				Rest::execute('placement.unbind', [
					'PLACEMENT' => $code,
					'HANDLER'   => $script_url,
				]);
			} catch (\Exception $e) {
			}
		}
	}

}