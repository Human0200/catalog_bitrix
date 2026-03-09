<?php
/**
 * Forgotten basket synchronization agent
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option;

class FBasketSyncAgent
{
    const MODULE_ID = 'sproduction.integration';

	var $options;

	public static function set() {
		$result = true;
		$sync_schedule = Settings::get('fbasket_sync_schedule');
		self::remove();
		// Create agent
		$agent_period = false;
		if ($sync_schedule == '1h') {
			$agent_period = 3600;
		} elseif ($sync_schedule == '1d') {
			$agent_period = 86400;
		}
		if ($agent_period) {
			\CAgent::AddAgent('\SProduction\Integration\FBasketSyncAgent::run();', self::MODULE_ID, 'N', $agent_period);
		}
		return $result;
	}

	public static function remove() {
		$result = true;
		// Remove agent
		\CAgent::RemoveAgent('\SProduction\Integration\FBasketSyncAgent::run();', self::MODULE_ID);
		return $result;
	}

	// Run sync
	public static function run() {
		$sync_active = Settings::get('active');
		$fbasket_sync_active = Settings::get('fbasket_active');
		if ($sync_active && $fbasket_sync_active) {
			\SProdIntegration::Log('(FBasketSyncAgent::run) start');
			$sync_schedule = Settings::get('fbasket_sync_schedule');
			$agent_period = false;
			if ($sync_schedule == '1h') {
				$agent_period = 3600;
			} elseif ($sync_schedule == '1d') {
				$agent_period = 86400;
			}
			// Pass period 2x agent period for synchronization
			$sync_period = $agent_period ? $agent_period * 2 : 0;
			FBasketSync::runSync($sync_period);
			\SProdIntegration::Log('(FBasketSyncAgent::run) finish');
		}
		return '\SProduction\Integration\FBasketSyncAgent::run();';
	}

	public static function check() {
		$result = false;
		$db = \CAgent::GetList(['NAME' => 'ASC'], [
			'MODULE_ID' => self::MODULE_ID,
			'NAME' => '\SProduction\Integration\FBasketSyncAgent::run();'
		]);
		if ($db->Fetch()) {
			$result = true;
		}
		return $result;
	}
}