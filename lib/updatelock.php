<?php
/**
 *    Orders and deals update lock
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option;

class UpdateLock
{
	public static function save($entity_id, $entity_type, $object) {
		$hash = self::getHash($object);
		LockTable::save([
			'type' => $entity_type,
			'entity_id' => $entity_id,
			'hash' => $hash,
		]);
		\SProdIntegration::Log('(UpdateLock) ' . $entity_type . ' id ' . $entity_id . ' save hash ' . $hash);
		return true;
	}

	public static function isChanged($entity_id, $entity_type, $object, $update=false) {
		$res = true;
		$orders = LockTable::getList([
			'filter' => [
				'type' => $entity_type,
				'entity_id' => $entity_id
			]
		]);
		$old_hash = '';
		if (!empty($orders)) {
			$old_hash = $orders[0]['hash'];
		}
		$new_hash = self::getHash($object);
		if ($old_hash == $new_hash) {
			$res = false;
		}
		if ($res && $update) {
			self::save($entity_id, $entity_type, $object);
		}
		\SProdIntegration::Log('(UpdateLock) ' . $entity_type . ' id ' . $entity_id . ' has '.($res?'changed':'no changes'));
		return $res;
	}

	protected static function getHash($object) {
		$order_str = serialize($object);
		$hash = md5($order_str);
		return $hash;
	}
}
