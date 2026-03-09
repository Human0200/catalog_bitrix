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
    Bitrix\Main\Config\Option,
    Bitrix\Main\Application;

class OrderProcessLock
{
	const LT_TYPE = 'process';
	const MAX_WAIT_TIME = 10;

	public static function set($item_id) {
		$connection = \Bitrix\Main\Application::getConnection();
		$tableName = LockTable::getTableName();

		try {
			// Блокируем таблицу для записи (другие процессы будут ждать)
			$connection->query("LOCK TABLES `" . $tableName . "` WRITE");

			try {
				// Проверяем существование блокировки прямым SQL запросом
				$existing = $connection->queryScalar(
					"SELECT COUNT(*) FROM `" . $tableName . "` WHERE `type` = '" . self::LT_TYPE . "' AND `entity_id` = '" . $connection->getSqlHelper()->forSql($item_id) . "'"
				);

				if ($existing > 0) {
					\SProdIntegration::Log('(OrderProcessLock) item ' . $item_id . ' lock already exists');
					return false;
				}

				// Создаем блокировку прямым SQL запросом
				$currentTime = time();
				$connection->query(
					"INSERT INTO `" . $tableName . "` (`type`, `entity_id`, `time`) VALUES ('" . self::LT_TYPE . "', '" . $connection->getSqlHelper()->forSql($item_id) . "', '" . $currentTime . "')"
				);
				\SProdIntegration::Log('(OrderProcessLock) item ' . $item_id . ' lock set');
				return true;

			} finally {
				// Всегда снимаем блокировку таблицы
				$connection->query("UNLOCK TABLES");
			}

		} catch (\Exception $e) {
			\SProdIntegration::Log('(OrderProcessLock) error setting lock for item ' . $item_id . ': ' . $e->getMessage());
			throw $e;
		}
	}

	public static function remove($item_id) {
		LockTable::delLock($item_id, self::LT_TYPE);
		\SProdIntegration::Log('(OrderProcessLock) item '.$item_id.' lock remove');
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
		\SProdIntegration::Log('(OrderProcessLock) item '.$item_id.' lock check ' . $res);
		return $res;
	}

	public static function wait($item_id) {
		//if (Settings::get('process_lock_enable') == 'Y') {
		// Check for locking timeout
		$lock_time = self::check($item_id);
		if ($lock_time && $lock_time < (time() - self::MAX_WAIT_TIME)) {
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
			\SProdIntegration::Log('(OrderProcessLock) item ' . $item_id . ' lock waited ' . $i . ' sec');
		}
		//}
		return $i;
	}
}
