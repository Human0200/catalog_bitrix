<?php
/**
 * Functions for Box mode of synchronization
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main;

class BoxMode
{
    const MODULE_ID = 'sproduction.integration';

	public static function findDealByOrder($order_id) {
		$deal_id = false;
		if(\Bitrix\Main\Loader::includeModule('crm') && class_exists('Bitrix\Crm\Binding\OrderEntityTable')) {
			$res = \Bitrix\Crm\Binding\OrderEntityTable::getOwnerByOrderId($order_id);
			$deal_id = $res['OWNER_ID'];
		}
		\SProdIntegration::Log('(BoxMode::findOrderByDeal) finded deal ' . $deal_id . ' by order ' . $order_id);
		return $deal_id;
	}

	public static function findOrderByDeal($deal_id) {
		$order_id = false;
		if(\Bitrix\Main\Loader::includeModule('crm') && class_exists('Bitrix\Crm\Binding\OrderEntityTable')) {
			$res = \Bitrix\Crm\Binding\OrderEntityTable::getOrderIdsByOwner($deal_id, 2);
			$order_id = (int)$res[0];
		}
		\SProdIntegration::Log('(BoxMode::findOrderByDeal) finded order ' . $order_id . ' by deal ' . $deal_id);
		return $order_id;
	}
}
