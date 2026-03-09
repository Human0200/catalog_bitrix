<?php
/**
 * Utilities
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

class Utilities
{
    const MODULE_ID = 'sproduction.integration';

	/**
	 * Convert encoding to UTF-8 (if needed)
	 */
	public static function convEncToUtf($value) {
		if ( ! \SProdIntegration::isUtf()) {
			$value = \Bitrix\Main\Text\Encoding::convertEncoding($value, "Windows-1251", "UTF-8");
		}
		return $value;
	}

	/**
	 * Convert encoding to WINDOWS-1251 (if needed)
	 */
	public static function convEncToWin($value) {
		if ( ! \SProdIntegration::isUtf()) {
			$value = \Bitrix\Main\Text\Encoding::convertEncoding($value, "UTF-8", "Windows-1251");
		}
		return $value;
	}

	/**
	 * Convert encoding for portal encoding
	 */
	public static function convEncForDeal($value) {
		return self::convEncToUtf($value);
	}

	/**
	 * Convert encoding for site encoding
	 */
	public static function convEncForOrder($value) {
		return self::convEncToWin($value);
	}

	/**
	 * Get formatted field for send file to CRM entity
	 */
	public static function getFileFieldForCrm($file_path) {
		$field = [];
		$name = pathinfo($file_path, PATHINFO_BASENAME);
		$data = file_get_contents($file_path);
		$field['fileData'] = [
			$name,
			base64_encode($data)
		];
		$field['fileId'] = $file_path;
		return $field;
	}

}
