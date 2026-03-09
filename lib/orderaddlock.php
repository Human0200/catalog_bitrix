<?php
/**
 *    Orders adding lock
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option,
    Bitrix\Main\Application;

class OrderAddLock
{
	const LT_TYPE = 'new_order';

	public static function add($order_id) {
		$connection = \Bitrix\Main\Application::getConnection();
		$tableName = LockTable::getTableName();

		try {
			// Блокируем таблицу для записи (другие процессы будут ждать)
			$connection->query("LOCK TABLES `" . $tableName . "` WRITE");

			try {
				// Проверяем существование блокировки прямым SQL запросом
				$existing = $connection->queryScalar(
					"SELECT COUNT(*) FROM `" . $tableName . "` WHERE `type` = '" . self::LT_TYPE . "' AND `entity_id` = '" . $connection->getSqlHelper()->forSql($order_id) . "'"
				);

				if ($existing > 0) {
					\SProdIntegration::Log('(OrderAddLock) item ' . $order_id . ' lock already exists');
					return false;
				}

				// Создаем блокировку прямым SQL запросом
				$currentTime = time();
				$connection->query(
					"INSERT INTO `" . $tableName . "` (`type`, `entity_id`, `time`) VALUES ('" . self::LT_TYPE . "', '" . $connection->getSqlHelper()->forSql($order_id) . "', '" . $currentTime . "')"
				);
				\SProdIntegration::Log('(OrderAddLock) item ' . $order_id . ' lock add "' . $currentTime . '"');
				return true;

			} finally {
				// Всегда снимаем блокировку таблицы
				$connection->query("UNLOCK TABLES");
			}

		} catch (\Exception $e) {
			\SProdIntegration::Log('(OrderAddLock) error adding lock for item ' . $order_id . ': ' . $e->getMessage());
			throw $e;
		}
	}

	public static function delete($order_id) {
		LockTable::delLock($order_id, self::LT_TYPE);
		\SProdIntegration::Log('(OrderAddLock) item '.$order_id.' lock delete');
		return true;
	}

	public static function check($order_id, $delete=false) {
		$res = false;
		$i = 0;
		do {
			if (isset($orders)) {
				usleep(50000);
			}
			$orders = LockTable::getList([
				'filter' => [
					'type'      => self::LT_TYPE,
					'entity_id' => $order_id
				]
			]);
			$i++;
		} while(empty($orders) && $i < 3);
		if (!empty($orders)) {
			$res = $orders[0]['time'];
		}
		if ($delete) {
			self::delete($order_id);
		}
		\SProdIntegration::Log('(OrderAddLock) item '.$order_id.' lock check "' . $res . '"');
		return $res;
	}
}
