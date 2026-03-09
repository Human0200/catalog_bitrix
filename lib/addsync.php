<?php
/**
 * Additional synchronization
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option;

class AddSync
{
    const MODULE_ID = 'sproduction.integration';

	var $options;

	public static function set() {
		$result = true;
		$sync_schedule = Settings::get('add_sync_schedule');
		self::remove();
		// Create agent
		$agent_period = false;
		if ($sync_schedule == '1h') {
			$agent_period = 3600;
		} elseif ($sync_schedule == '1d') {
			$agent_period = 86400;
		}
		if ($agent_period) {
			\CAgent::AddAgent('\SProduction\Integration\AddSync::run();', self::MODULE_ID, 'N', $agent_period);
		}
		return $result;
	}

	public static function remove() {
		$result = true;
		// Remove agent
		\CAgent::RemoveAgent('\SProduction\Integration\AddSync::run();', self::MODULE_ID);
		return $result;
	}

	// Run sync
	public static function run() {
		$sync_active = Settings::get('active');
		if ($sync_active) {
			$sync_period = 0;
			$sync_schedule = Settings::get('add_sync_schedule');
			if ($sync_schedule == '1h') {
				$sync_period = 3600 * 2;
			}
			elseif ($sync_schedule != '') {
				$sync_period = 3600 * 24 * 2;
			}
			\SProdIntegration::Log('(AddSync::run) start');
			Integration::syncStoreToCRM($sync_period);
			\SProdIntegration::Log('(AddSync::run) finish');
		}
		return '\SProduction\Integration\AddSync::run();';
	}

	public static function check() {
		$result = false;
		$db = \CAgent::GetList(['NAME' => 'ASC'], [
			'MODULE_ID' => self::MODULE_ID,
			'NAME' => '\SProduction\Integration\AddSync::run();'
		]);
		if ($db->Fetch()) {
			$result = true;
		}
		return $result;
	}
}
