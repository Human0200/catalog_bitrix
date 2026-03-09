<?php
/**
 *  Orders processing lock
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option;

class DealProcessLock
{
	const LT_TYPE = 'deal';
	const MAX_WAIT_TIME = 10;

	public static function set($item_id) {
		if (!self::check($item_id)) {
			LockTable::add([
				'type'      => self::LT_TYPE,
				'entity_id' => $item_id,
			]);
			\SProdIntegration::Log('(DealProcessLock) item ' . $item_id . ' lock set');
		}
		return true;
	}

	public static function remove($item_id) {
		LockTable::delLock($item_id, self::LT_TYPE);
		\SProdIntegration::Log('(DealProcessLock) item ' . $item_id . ' lock remove');
		return true;
	}

	public static function check($item_id, $delete=false) {
		$res = false;
		$orders = LockTable::getList([
			'filter' => [
				'type' => self::LT_TYPE,
				'entity_id' => $item_id
			]
		]);
		if (!empty($orders)) {
			$res = $orders[0]['time'];
		}
		if ($delete) {
			self::remove($item_id);
		}
		\SProdIntegration::Log('(DealProcessLock) item ' . $item_id . ' lock check ' . $res);
		return $res;
	}

	/**
	 * Check for locking timeout
	 */
	public static function hasExpired($item_id) {
		$result = false;
		$lock_time = self::check($item_id);
		if ($lock_time && $lock_time < (time() - self::MAX_WAIT_TIME)) {
			$result = true;
		}
		return $result;
	}

	public static function wait($item_id) {
		//if (Settings::get('process_lock_enable') == 'Y') {
		// Check for locking timeout
		if (self::hasExpired($item_id)) {
			self::remove($item_id);
			$lock_time = false;
		}
		// Sleep if locked
		$i = 0;
		while ($lock_time && $i < self::MAX_WAIT_TIME) {
			sleep(1);
			$lock_time = self::check($item_id);
			$i++;
		}
		if ($i) {
			\SProdIntegration::Log('(DealProcessLock) item ' . $item_id . ' lock waited ' . $i . ' sec');
		}
		//}
		return $i;
	}
}
