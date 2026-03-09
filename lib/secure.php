<?php
/**
 *    Secure functions
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
	Bitrix\Main\Localization\Loc,
    Bitrix\Main\Config\Option;

class Secure
{
    const MODULE_ID = 'sproduction.integration';
    const SECURE_SALT = 'jNiel.mkKkpt434nlkHKl,mdfpfmmk';

	public static function getAjaxSCode() {
		$secure_str = self::SECURE_SALT;
		$secure_str .= $_SERVER['SERVER_NAME'];
		$secure_str .= date('dmY');
		return md5($secure_str);
	}

	public static function checkAjaxSCode($inp_code) {
		$result = false;
		$check_code = self::getAjaxSCode();
		if ($check_code == $inp_code) {
			$result = true;
		}
		return $result;
	}
}
