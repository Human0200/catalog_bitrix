<?php
/**
 *    Rest Cache
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

class RestCache
{
	const METHODS = ['profile', 'app.info', 'crm.deal.fields', 'crm.deal.userfield.list', 'catalog.catalog.list', 'crm.product.fields', 'catalog.product.getFieldsByFilter', 'catalog.product.offer.getFieldsByFilter'];

	private static $CACHE = [];

	protected static function checkMethod($method) {
		$result = false;
		if (in_array($method, self::METHODS)) {
			$result = true;
		}
		return $result;
	}

	protected static function getId($method, $params, $only_res) {
		return $method . md5(serialize($params)) . $only_res;
	}

	public static function add($method, $params, $only_res, $value, $force_cache=false) {
		if (self::checkMethod($method) || $force_cache) {
			self::$CACHE[self::getId($method, $params, $only_res)] = $value;
		}
	}

	public static function hasValue($method, $params, $only_res) {
		$result = false;
		if (isset(self::$CACHE[self::getId($method, $params, $only_res)])) {
			$result = true;
		}
		return $result;
	}

	public static function get($method, $params, $only_res) {
		$result = false;
		if (isset(self::$CACHE[self::getId($method, $params, $only_res)]) && !is_null(self::$CACHE[self::getId($method, $params, $only_res)])) {
			$result = self::$CACHE[self::getId($method, $params, $only_res)];
		}
		return $result;
	}
}
