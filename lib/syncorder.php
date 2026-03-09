<?php
/**
 * Synchronization from deal to order
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
	Bitrix\Main\Type,
	Bitrix\Main\Entity,
	Bitrix\Main\Localization\Loc,
	Bitrix\Main\SiteTable,
	Bitrix\Sale;

Loc::loadMessages(__FILE__);

class SyncOrder
{
    const MODULE_ID = 'sproduction.integration';


	/**
	 * Sync deal with order
	 */
	public static function runSync($deal, $create_allow=true) {
		$deal_id = $deal['ID'];
		// Check base synchronization terms
		if (!\SProdIntegration::isSyncAllow(strtotime($deal['DATE_CREATE']))) {
			return;
		}
		// Order ID
		if (Settings::getSyncMode() == Settings::SYNC_MODE_NORMAL) {
			$order_id = self::getOrderIdByDeal($deal);
			// Try to create order
			if (!$order_id && $create_allow) {
				// Lock for other process
				if (DealProcessLock::hasExpired($deal_id)) {
					DealProcessLock::remove($deal_id);
				}
				if (!DealProcessLock::check($deal_id)) {
					DealProcessLock::set($deal_id);
					// Create order
					$order_id = self::createOrder($deal);
					// Save ID in the deal
					if ($order_id) {
						self::saveOrderIdAfterCreate($deal_id, $order_id);
						DealProcessLock::remove($deal_id);
						// Update deal by created order
						$order      = Sale\Order::load($order_id);
						$order_data = StoreData::getOrderInfo($order);
						SyncDeal::runSync($order_data, false, SyncDeal::MODE_AFTER_ADD);
					}
					else {
						DealProcessLock::remove($deal_id);
					}
				}
			}
		}
		else {
			$order_id = BoxMode::findOrderByDeal($deal_id);
		}
		if ($order_id) {
			// Waiting, if the order locked
			if (OrderProcessLock::wait($order_id) > 0) {
				$deals = PortalData::getDeal([$deal['ID']]);
				$deal = $deals[0];
				\SProdIntegration::Log('(SyncOrder::runSync) fresh deal data ' . print_r($deal, true));
			}
			if (!$deal['ID']) {
				return;
			}
			// Process the event handlers
			foreach (GetModuleEvents(Integration::MODULE_ID, "OnBeforeOrderUpdate", true) as $event) {
				$deal = ExecuteModuleEventEx($event, [$deal]);
			}
			//\SProdIntegration::Log(print_r($deal, true));
			// Check source of deal
			$source_id = Settings::get("source_id");
			if ($source_id && $source_id != $deal['ORIGINATOR_ID']) {
				\SProdIntegration::Log('(SyncOrder::runSync) source of deal error');
				return;
			}
			// Check order data
			$order_data = [];
			if ($order_id) {
				$order = Sale\Order::load($order_id);
				$order_data = StoreData::getOrderInfo($order);
			}
			//\SProdIntegration::Log('(SyncOrder::runSync) order_data ' . print_r($order_data, true));
			if (empty($order_data)) {
				\SProdIntegration::Log('(SyncOrder::runSync) order data error');
				return;
			}
			// Check start date
			$start_date_ts = PortalData::getStartDateTs();
			if ($start_date_ts && $order_data['DATE_INSERT'] < $start_date_ts) {
				\SProdIntegration::Log('(SyncOrder::runSync) start date error');
				return;
			}
			// Get profile
			$profile = ProfilesTable::getOrderProfile($order_data, $deal);
			if ( ! $profile) {
				\SProdIntegration::Log('(SyncOrder::runSync) profile info error');
				return;
			}
			// Check category
			if ($profile['options']['deal_category'] != $deal['CATEGORY_ID']) {
				\SProdIntegration::Log('(SyncOrder::runSync) deal category error');
				return;
			}

			// Get deal info
			$deal_info = PortalData::getDealInfo($profile, $deal['ID']);

			// Check if any deal fields have changed
			$deal_fields_to_check = self::getDealFieldsForSync($deal, $deal_info, $profile);
			if (!FieldUpdateLock::hasAnyFieldChanged($deal_id, 'deal', $deal_fields_to_check)) {
				\SProdIntegration::Log('(SyncOrder::runSync) no deal fields changed');
				return;
			}

			// Lock the order for other processes
			OrderProcessLock::set($order_id);
//			// Remember date modify of current deal for future checks
//			SyncDealControl::setDealLastCallHashByDeal($deal['ID']);
			// Update order
			StoreOrder::updateOrder($order_data, $deal, $deal_info, $profile);
			// Update field hashes after successful sync (Deal -> Order)
			SyncHashManager::updateHashesAfterSync($deal_id, 'deal', $order_id, 'order', $deal);
			// Unlock order
			OrderProcessLock::remove($order_id);
		}
	}

	/**
	 * Check if we need to create new order
	 */
	public static function getOrderIdByDeal($deal) {
		return $deal[Settings::getOrderIDField()];
	}

	/**
	 * Create new order
	 */

	public static function createOrder($deal) {
		$order_id = false;
		// Find profile
		$profile = ProfilesTable::getProfileByDeal($deal);
		if ($profile && $profile['neworder']['active']) {
			// Get info
			$deal_info = PortalData::getDealInfo($profile, $deal['ID']);
			// Create order
			$order_id = StoreOrder::createOrder($deal, $deal_info, $profile);
		}
		return $order_id;
	}

	/**
	 * Get deal fields that should be monitored for changes
	 */
	protected static function getDealFieldsForSync($deal, $deal_info, $profile) {
		$fields = [];

		// Add basic deal fields
		$basic_fields = ['TITLE', 'STAGE_ID', 'OPPORTUNITY', 'CURRENCY_ID', 'ASSIGNED_BY_ID', 'COMMENTS'];
		foreach ($basic_fields as $field) {
			if (isset($deal[$field])) {
				$fields[$field] = $deal[$field];
			}
		}

		// Add custom fields from profile mappings
		if (isset($profile['props']['comp_table'])) {
			foreach ($profile['props']['comp_table'] as $deal_field => $mapping) {
				if (isset($deal[$deal_field])) {
					$fields[$deal_field] = $deal[$deal_field];
				}
			}
		}

		if (isset($profile['other']['comp_table'])) {
			foreach ($profile['other']['comp_table'] as $deal_field => $mapping) {
				if (isset($deal[$deal_field])) {
					$fields[$deal_field] = $deal[$deal_field];
				}
			}
		}

		return $fields;
	}

	/**
	 * Save order id info in the deal
	 */

	public static function saveOrderIdAfterCreate($deal_id, $order_id) {
		$fields = [
			Settings::getOrderIDField() => $order_id
		];
		$source_id = Settings::get("source_id");
		if ($source_id) {
			$fields['ORIGINATOR_ID'] = $source_id;
		}
		SyncDeal::updateFields($deal_id, $order_id, $fields);
	}

}
