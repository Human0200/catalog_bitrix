<?php
/**
 * Control of synchronization from store to deal
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

class SyncDealControl
{
	public static function dealChangedBeforeOrder($order_data, $deal) {
		$result = false;
		$check_interval = (int)Settings::get('modify_deal_order_interval') ?? 2; // seconds
		$deal_date_modify = (is_array($deal) && isset($deal['DATE_MODIFY'])) ? strtotime($deal['DATE_MODIFY']) : false;
		$order_date_modify = $order_data['DATE_UPDATE'] ?? false;
		if ($deal_date_modify && $order_date_modify &&
			$deal_date_modify <= $order_date_modify && $deal_date_modify >= ($order_date_modify - $check_interval)) {
			$result = true;
		}
		return $result;
	}
}
