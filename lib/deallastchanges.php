<?php
/**
 * Control of deal changes while synchronization
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option;

class DealLastChanges
{
	const LT_TYPE = 'deal_last_changes';
	const MAX_WAIT_TIME = 10;
	static $lock_hash;

	public static function set($item_id) {
		self::$lock_hash = self::getHash();
		LockTable::save([
			'type'      => self::LT_TYPE,
			'entity_id' => $item_id,
			'hash' => self::$lock_hash,
		]);
		\SProdIntegration::Log('(DealLastChanges) item ' . $item_id . ' lock set');
		return true;
	}

	public static function get($item_id) {
		$res = '';
		$records = LockTable::getList([
			'filter' => [
				'type' => self::LT_TYPE,
				'entity_id' => $item_id
			]
		]);
		if (!empty($records)) {
			$res = $records[0]['hash'];
		}
		\SProdIntegration::Log('(DealLastChanges) item ' . $item_id . ' lock: ' . $res);
		return $res;
	}

	public static function remove($item_id) {
		$db_lock_hash = self::get($item_id);
		if ($db_lock_hash == self::$lock_hash) {
			LockTable::delLock($item_id, self::LT_TYPE);
			\SProdIntegration::Log('(DealLastChanges) item ' . $item_id . ' lock remove');
		}
		return true;
	}

	protected static function getHash() {
		return md5(microtime(true) . rand(1, 1000000));
	}

	public static function wait($item_id) {
		// Sleep if locked
		$i = 0;
		while (self::get($item_id) != '' && $i < self::MAX_WAIT_TIME) {
			sleep(1);
			$i++;
		}
		if ($i) {
			\SProdIntegration::Log('(DealLastChanges) item ' . $item_id . ' lock waited ' . $i . ' sec');
		}
		return $i;
	}
}
