<?php
/**
 * Offline events
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

\Bitrix\Main\Loader::includeModule("sale");

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option,
	Bitrix\Sale;

class OfflineEvents
{
    const MODULE_ID = 'sproduction.integration';

	public static function eventsBind() {
		try {
			Rest::execute('event.bind', [
				'event'      => 'ONCRMDEALUPDATE',
				'event_type' => 'offline',
			]);
			Rest::execute('event.bind', [
				'event'      => 'ONCRMDEALADD',
				'event_type' => 'offline',
			]);
		} catch (\Exception $e) {}
	}

	public static function eventsUnbind() {
		try {
			Rest::execute('event.unbind', [
				'event'      => 'ONCRMDEALUPDATE',
				'event_type' => 'offline',
			]);
			Rest::execute('event.unbind', [
				'event'      => 'ONCRMDEALADD',
				'event_type' => 'offline',
			]);
		} catch (\Exception $e) {}
	}

    public static function getChangedDeals($type='update') {
    	$list = [];
	    $res = Rest::execute('event.offline.get', []);
	    foreach ($res['events'] as $item) {
		    if ($type == 'update' && $item['EVENT_NAME'] == 'ONCRMDEALUPDATE') {
		    	$deal_id = (int)$item['EVENT_DATA']['FIELDS']['ID'];
		    	if ($deal_id) {
		    		$list[] = $deal_id . 'u';
			    }
		    }
		    if ($type == 'add' && $item['EVENT_NAME'] == 'ONCRMDEALADD') {
		    	$deal_id = (int)$item['EVENT_DATA']['FIELDS']['ID'];
		    	if ($deal_id) {
		    		$list[] = $deal_id . 'a';
			    }
		    }
	    }
	    return $list;
    }

    public static function processEvents($other_deals_acts=[]) {
	    // Check base synchronization terms
	    if (!\SProdIntegration::isSyncAllow()) {
		    return;
	    }
		// Additional deals
	    $deals_acts = self::getChangedDeals();
	    $deals_acts = array_unique(array_merge($deals_acts, $other_deals_acts));
	    \SProdIntegration::Log('(processEvents) changed deals: ' . print_r($deals_acts, true));
	    // Wait for changes fix
	    if (Settings::get('deals_last_change_ts') > time() - 2) {
		    \SProdIntegration::Log('(processEvents) wait 2 sec');
	    	sleep(2);
	    }
		// Deals data
	    $deal_ids = [];
	    $deals_acts_assoc = [];
	    foreach ($deals_acts as $deal_act) {
			// Get ID and type of event from 12345u format
		    $deal_id = (int)mb_substr($deal_act, 0, -1);
			if ($deal_id) {
				$action_type = mb_substr($deal_act, -1, 1);
				// Add priority
				$deals_acts_assoc[$deal_id] = (!isset($deal_ids[$deal_id]) || $deal_ids[$deal_id] == 'u') ? $action_type : 'a';
			}
	    }
	    $deal_ids = array_keys($deals_acts_assoc);
	    $deals = PortalData::getDeal($deal_ids);
		// Actions process
	    foreach ($deals as $deal) {
			DealLastChanges::set($deal['ID']);
		    $deal_id = $deal['ID'];
			$deal_act = $deals_acts_assoc[$deal_id];
		    \SProdIntegration::Log('(processEvents) act "' . $deal_act . '" for deal ' . print_r($deal, true));
			// Deal updated
			if ($deal_act == 'u') {
				// Change fields in the order
				$opt_direction = Settings::get("direction");
				if ( ! $opt_direction || $opt_direction == 'full' || $opt_direction == 'ctos') {
			        SyncOrder::runSync($deal);
				}
			}
			// Deal added
			else {
				if (Settings::getSyncMode() == Settings::SYNC_MODE_NORMAL) {
					// Create order
					SyncOrder::runSync($deal);
				}
				else {
					// Update deal
					$order_id = BoxMode::findOrderByDeal($deal['ID']);
					if ( ! $order_id) {
						continue;
					}
					// Change fields in the order
					$order      = Sale\Order::load($order_id);
					$order_data = StoreData::getOrderInfo($order);
			        SyncDeal::runSync($order_data, false, SyncDeal::MODE_AFTER_ADD);
				}
			}
			DealLastChanges::remove($deal_id);
	    }
	}
}
