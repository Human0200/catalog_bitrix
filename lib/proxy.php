<?php
/**
 * Proxy use
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option;

class Proxy
{
    public static function getRandom() {
		$result = false;
		$list = self::getList();
		if (!empty($list)) {
			$last = count($list) - 1;
			$i = rand(0, $last);
			$result = $list[$i];
		}
		return $result;
    }

    public static function getList() {
		$list = [];
	    $proxy_list = Settings::get('proxy_list', true);
		// Check items
	    if (!empty($proxy_list)) {
		    foreach ($proxy_list as $item) {
			    if ($item['ip'] && $item['port']) {
				    $list[] = $item;
			    }
		    }
	    }
		return $list;
    }
}
