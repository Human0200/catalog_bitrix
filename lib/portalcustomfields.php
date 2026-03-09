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

class PortalCustomFields {
	const MODULE_ID = 'sproduction.integration';
	public const HANDLER_URL = '/bitrix/sprod_integr_custom_fields.php';

	public static function setAll() {
		if ( ! Rest::checkConnection()) {
			return;
		}
		$app_info = Rest::getAppInfo();
		$source_name = Settings::get('source_name');
		// Set fields
		if ( ! PortalCustomFields::checkItem(self::getFieldCode('sprodintegrorderuser'))) {
			$script_url = $app_info['site'] . self::HANDLER_URL;
			$title = Loc::getMessage("SP_CI_CUSTOMFIELDS_ORDERUSER") . ($source_name ? (' (' . $source_name . ')') : '');
			if ( ! SProdIntegration::isUtf()) {
				$title = \Bitrix\Main\Text\Encoding::convertEncoding($title, "Windows-1251", "UTF-8");
			}
			self::setItem(self::getFieldCode('sprodintegrorderuser'), $script_url, $title, Loc::getMessage("SP_CI_CUSTOMFIELDS_ORDERUSER_DESCR"));
		}
	}

	public static function removeAll() {
		if ( ! Rest::checkConnection()) {
			return;
		}
		// Remove fields
		PortalCustomFields::removeItem(self::getFieldCode('sprodintegrorderuser'));
	}

	public static function setItem($code, $script_url, $title, $description='') {
		try {
			Rest::execute('userfieldtype.add', [
				'USER_TYPE_ID' => $code,
				'HANDLER'      => $script_url,
				'TITLE'        => $title,
				'DESCRIPTION'  => $description,
			]);
		} catch (\Exception $e) {
		}
	}

	public static function checkItem($code) {
		$list = Rest::execute('userfieldtype.list');
		$check = false;
		foreach ($list as $field) {
			if ($field['USER_TYPE_ID'] == $code) {
				$check = true;
			}
		}
		return $check;
	}

	public static function removeItem($code) {
		if (self::checkItem($code)) {
			try {
				Rest::execute('userfieldtype.delete', [
					'USER_TYPE_ID' => $code,
				]);
			} catch (\Exception $e) {
			}
		}
	}

	public static function getFieldCode($code) {
		$add_text = substr(md5(str_replace('www.', '', $_SERVER['SERVER_NAME'])),0, 5);
		return $code . '_' . $add_text;
	}
}