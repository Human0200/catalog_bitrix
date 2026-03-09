<?php
/**
 * Access control for remote monitoring of module state
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

class RemoteMonitorAccess {

	const SETTINGS_ACTIVE_KEY = 'remote_monitor_active';
	const SETTINGS_SKEY_KEY = 'remote_monitor_skey';
	const SALT = 'kemLIienlkgfd3-imrmermoorm';

	public static function checkAccess(string $key): bool {
		$result = false;
		if (strlen($key) > 0 && $key == self::getAccessKey()) {
			$result = true;
		}
		return $result;
	}

	public static function getAccessKey(): string {
		return Settings::get(self::SETTINGS_SKEY_KEY);
	}

	public static function generateAccessKey(): bool {
		Settings::save(self::SETTINGS_SKEY_KEY, self::generateKey());
		return true;
	}

	protected static function generateKey(): string {
		return md5(self::SALT . strval(microtime(true)));
	}

	public static function clearAccessKey(): bool {
		Settings::save(self::SETTINGS_SKEY_KEY, '');
		return true;
	}

	public static function isActive(): bool {
		return Settings::get(self::SETTINGS_ACTIVE_KEY);
	}

	public static function setActive(bool $value): void {
		Settings::save(self::SETTINGS_ACTIVE_KEY, $value);
	}

	public static function getLink(): string {
		$portal = Settings::get("portal");
		return $portal ? ($portal . '/market/detail/sproduction.gibkaya_integratsiya_zakazov_s_bitriks24_monitor/') : '';
	}
}
