<?php
/**
 * Remote diagnostics page
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Cassandra\Set,
	Bitrix\Main\Localization\Loc;

class RemoteDiagAccess
{
	const MODULE_ID = 'sproduction.integration';
	const DATE_FORMAT = 'd.m.Y H:i:s';
	const SECRET_SALT = 'NEiKnekm3-5,lkrioji';

	public static function checkAccess($secure_code) {
		$result = false;
		$check_code = self::getCode();
		$state = self::getState();
		if ($secure_code == $check_code && $state['code'] == 'active') {
			$result = true;
		}
		return $result;
	}

	public static function isActive() {
		$result = (Settings::get("ra_active") == 'Y');
		return $result;
	}

	public static function setActive($value) {
		$old_active = self::isActive();
		// Save Active flag
		Settings::save('ra_active', ($value ? 'Y' : 'N'));
		// Check changes
		$new_active = self::isActive();
		if ($new_active && !$old_active) {
			self::generateCode();
			if (!self::getTimeTS()) {
				self::setTime(date(self::DATE_FORMAT, time() + 24 * 3600 * 3));
			}
		}
	}

	public static function getTime() {
		$result = Settings::get('ra_end_time');
		if ($result) {
			$result = date(self::DATE_FORMAT, $result);
		}
		return $result;
	}

	public static function getTimeTS() {
		$result = Settings::get('ra_end_time');
		if ($result) {
			$result = (int)$result;
		}
		return $result;
	}

	public static function setTime($datetime) {
		Settings::save('ra_end_time', strtotime($datetime));
	}

	public static function getCode() {
		$result = Settings::get('ra_code');
		return $result;
	}

	public static function setCode($code) {
		Settings::save('ra_code', $code);
	}

	public static function generateCode() {
		$string = $_SERVER['SERVER_NAME'] . self::SECRET_SALT . strval(microtime(true));
		$code = md5($string);
		self::setCode($code);
	}

	public static function getLink() {
		$result = '';
		$code = self::getCode();
		if ($code) {
			$result = 'https://' . $_SERVER['SERVER_NAME'] . '/bitrix/sprod_integr_diagnostics.php?sc=' . $code;
		}
		return $result;
	}

	public static function getState() {
		$states = [
			'active' => [
				'code' => 'active',
				'title' => Loc::getMessage("SP_CI_REMOTEDIAGACCESS_STATE_ACTIVE"),
			],
			'not_active' => [
				'code' => 'not_active',
				'title' => Loc::getMessage("SP_CI_REMOTEDIAGACCESS_STATE_NOT_ACTIVE"),
			],
			'active_expired' => [
				'code' => 'active_expired',
				'title' => Loc::getMessage("SP_CI_REMOTEDIAGACCESS_STATE_ACTIVE_EXPIRED"),
			],
		];
		if (!self::isActive()) {
			$state = 'not_active';
		}
		else {
			if (self::getTimeTS() && self::getTimeTS() > time()) {
				$state = 'active';
			}
			else {
				$state = 'active_expired';
			}
		}
		return $states[$state];
	}
}