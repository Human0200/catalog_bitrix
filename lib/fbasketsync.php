<?php
/**
 * Synchronization of forgotten baskets with deals
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
	Bitrix\Main\Type,
	Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class FBasketSync
{
	const MODULE_ID = 'sproduction.integration';

	/**
	 * Run synchronization of forgotten baskets with deals
	 * This method should be called periodically (e.g., via cron)
	 *
	 * @param int $sync_period - Period in seconds for filtering updated deals (0 = use fbasket_hours setting)
	 */
	public static function runSync($sync_period = 0) {
		\SProdIntegration::Log('(FBasketSync::runSync) start synchronization');

		// Check if REST connection is available
		if (!Rest::checkConnection()) {
			\SProdIntegration::Log('(FBasketSync::runSync) REST connection not available');
			return false;
		}

		// Get baskets updated in the specified period
		$forgotten_hours = (int) Settings::get('fbasket_hours');
		$forgotten_hours = $forgotten_hours > 0 ? $forgotten_hours : 0;
		$date_to = new Type\DateTime();

		// Calculate date_from based on sync period and "sync start date" setting
		$date_from = null;
		if ($sync_period > 0) {
			$date_from = (new Type\DateTime())->add('-' . ($sync_period + $forgotten_hours) . ' seconds');
		}
		$start_date_ts = PortalData::getStartDateTs();
		if ($start_date_ts) {
			$start_date_from = Type\DateTime::createFromTimestamp($start_date_ts);
			if ($date_from === null || $start_date_from->getTimestamp() > $date_from->getTimestamp()) {
				$date_from = $start_date_from;
			}
		}
		if ($date_from) {
			\SProdIntegration::Log('(FBasketSync::runSync) filtering baskets from ' . $date_from->toString() . ' to ' . $date_to->toString());
		}

		$baskets = ForgottenBasket::getList($date_from, $date_to, null, 0, $forgotten_hours);
		\SProdIntegration::Log('(FBasketSync::runSync) found ' . count($baskets) . ' baskets updated before ' . $forgotten_hours . ' hours ago');

		if (empty($baskets)) {
			\SProdIntegration::Log('(FBasketSync::runSync) no baskets to sync');
			return true;
		}

		// Get active profiles
		$profiles = FbasketProfilesTable::getActiveList();
		if (empty($profiles)) {
			\SProdIntegration::Log('(FBasketSync::runSync) no active profiles found');
			return false;
		}

		// Build profile map by site_id
		$profiles_by_site = [];
		foreach ($profiles as $profile) {
			// Add profile by site if site is specified
			if ($profile['site']) {
				$profiles_by_site[$profile['site']] = $profile;
			} else {
				// Add profile without site binding (can be used as fallback)
				$profiles_by_site[''] = $profile;
			}
		}
		\SProdIntegration::Log('(FBasketSync::runSync) loaded ' . count($profiles) . ' active profiles');

		// Process each basket
		$stats = [
			'processed' => 0,
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors' => 0,
		];

		foreach ($baskets as $basket) {
			try {
				$result = self::processBasket($basket, $profiles_by_site);
				$stats['processed']++;
				if ($result === 'created') {
					$stats['created']++;
				} elseif ($result === 'updated') {
					$stats['updated']++;
				} elseif ($result === 'skipped') {
					$stats['skipped']++;
				}
			} catch (\Exception $e) {
				\SProdIntegration::Log('(FBasketSync::runSync) error processing basket ' . $basket['ID'] . ': ' . $e->getMessage());
				$stats['errors']++;
			}
		}

		\SProdIntegration::Log('(FBasketSync::runSync) sync completed: ' . print_r($stats, true));
		return true;
	}

	/**
	 * Process single basket
	 */
	public static function processBasket($basket, $profiles_by_site) {
		$basket_id = $basket['ID'];
		$site_id = $basket['SITE_ID'];
		$status_id = $basket['STATUS_ID'] ?? '(none)';
		$order_id = isset($basket['ORDER_ID']) ? (int) $basket['ORDER_ID'] : 0;
		\SProdIntegration::Log('(FBasketSync::processBasket) processing basket ' . $basket_id . ' for site ' . $site_id . ' STATUS_ID=' . $status_id . ' ORDER_ID=' . $order_id);

		// Find profile for this basket's site
		$profile = self::getProfileBySiteId($site_id, $profiles_by_site);
		if (!$profile) {
			// If no profile found for specific site, try to find profile without site binding
			$profile = self::getProfileBySiteId(null, $profiles_by_site);
		}
		if (!$profile) {
			\SProdIntegration::Log('(FBasketSync::processBasket) no profile found for site ' . $site_id . ' or without site binding');
			return 'skipped';
		}

		// Search for existing deal by basket ID
		$deal_id = self::findDealByBasket($basket_id, $profile);

		if ($deal_id) {
			// Update existing deal
			\SProdIntegration::Log('(FBasketSync::processBasket) found existing deal ' . $deal_id . ' for basket ' . $basket_id);
			self::updateDealFromBasket($deal_id, $basket, $profile);
			return 'updated';
		} else {
			// Create new deal
			\SProdIntegration::Log('(FBasketSync::processBasket) creating new deal for basket ' . $basket_id);
			$deal_id = self::createDealFromBasket($basket, $profile);
			if ($deal_id) {
				\SProdIntegration::Log('(FBasketSync::processBasket) created deal ' . $deal_id . ' for basket ' . $basket_id);
				return 'created';
			} else {
				\SProdIntegration::Log('(FBasketSync::processBasket) failed to create deal for basket ' . $basket_id);
				return 'skipped';
			}
		}
	}

	/**
	 * Get profile by site ID
	 */
	protected static function getProfileBySiteId($site_id, $profiles_by_site) {
		// First try to find profile for specific site
		if ($site_id && isset($profiles_by_site[$site_id])) {
			return $profiles_by_site[$site_id];
		}
		// Then try to find profile without site binding (null or empty string)
		if (isset($profiles_by_site['']) || isset($profiles_by_site[null])) {
			return $profiles_by_site[''] ?? $profiles_by_site[null];
		}
		return null;
	}

	/**
	 * Get basket ID field name from settings
	 */
	protected static function getBasketIdField() {
		return Settings::get('fbasket_deal_field') ?: 'ORIGIN_ID';
	}

	/**
	 * Find deal by basket ID
	 */
	protected static function findDealByBasket($basket_id, $profile) {
		$deal_id = false;

		// Use custom field to store basket ID (similar to order ID field)
		$basket_id_field = self::getBasketIdField();

		$filter = [
			'=' . $basket_id_field => $basket_id,
		];

		// Filter by category if specified in profile
		$category_id = (int) $profile['options']['deal_category'];
		if ($category_id) {
			$filter['=CATEGORY_ID'] = $category_id;
		}

		// Filter by source if specified
		$source_id = Settings::get("source_id");
		if ($source_id) {
			$filter['=ORIGINATOR_ID'] = $source_id;
		}

		\SProdIntegration::Log('(FBasketSync::findDealByBasket) searching deal for basket ' . $basket_id . ' with filter: ' . print_r($filter, true));

		try {
			$deals = Rest::execute('crm.deal.list', [
				'filter' => $filter,
				'select' => ['ID'],
				'start' => -1
			]);

			if (!empty($deals) && isset($deals[0]['ID'])) {
				$deal_id = (int) $deals[0]['ID'];
			}
		} catch (\Exception $e) {
			\SProdIntegration::Log('(FBasketSync::findDealByBasket) error searching deal: ' . $e->getMessage());
		}

		return $deal_id;
	}

	/**
	 * Create new deal from basket
	 */
	protected static function createDealFromBasket($basket, $profile) {
		$deal_id = false;

		// Get deal info structure
		$deal_info = PortalData::getDealInfo($profile);

		// Prepare deal fields
		$fields = self::getDealFields($basket, $profile, $deal_info, true);

		if (empty($fields)) {
			\SProdIntegration::Log('(FBasketSync::createDealFromBasket) empty fields for basket ' . $basket['ID']);
			return false;
		}

		// Process event handlers
		foreach (GetModuleEvents(Integration::MODULE_ID, "OnBeforeFBasketDealAdd", true) as $event) {
			$fields = ExecuteModuleEventEx($event, [$fields, $basket, $profile]);
		}

		\SProdIntegration::Log('(FBasketSync::createDealFromBasket) creating deal with fields: ' . print_r($fields, true));

		try {
			$deal_id = Rest::execute('crm.deal.add', ['fields' => $fields]);
			
			if ($deal_id) {
				\SProdIntegration::Log('(FBasketSync::createDealFromBasket) deal ' . $deal_id . ' created for basket ' . $basket['ID']);
				
				// Add products to deal
				self::updateDealProducts($deal_id, $basket, $deal_info);

				// Process event handlers
				foreach (GetModuleEvents(Integration::MODULE_ID, "OnAfterFBasketDealAdd", true) as $event) {
					ExecuteModuleEventEx($event, [$deal_id, $basket, $profile]);
				}

				// Send notification
				if (Settings::get('fbasket_notify_on_create')) {
					$msg = Loc::getMessage("SP_CI_FBASKET_DEAL_CREATED", [
						'#BASKET_ID#' => $basket['ID'],
						'#PRICE#' => $basket['PRICE_SUM'],
					]);
					CrmNotif::sendMsg($msg, 'FBASKETDEALCREATE', CrmNotif::TYPE_SUCCESS, $deal_id);
				}
			}
		} catch (\Exception $e) {
			\SProdIntegration::Log('(FBasketSync::createDealFromBasket) error creating deal: ' . $e->getMessage());
		}

		return $deal_id;
	}

	/**
	 * Update existing deal from basket
	 */
	protected static function updateDealFromBasket($deal_id, $basket, $profile) {
		// Get deal info
		$deal_info = PortalData::getDealInfo($profile, $deal_id);
		
		if (empty($deal_info['deal'])) {
			\SProdIntegration::Log('(FBasketSync::updateDealFromBasket) deal ' . $deal_id . ' not found');
			return false;
		}


		// Prepare updated fields
		$fields = self::getDealFields($basket, $profile, $deal_info, false);

		if (empty($fields)) {
			$current_stage = $deal_info['deal']['STAGE_ID'] ?? '';
			$new_stage = self::getStageForBasketStatus($basket, $profile);
			\SProdIntegration::Log('(FBasketSync::updateDealFromBasket) no fields to update for deal ' . $deal_id . ' basket STATUS_ID=' . ($basket['STATUS_ID'] ?? '') . ' current_stage=' . $current_stage . ' new_stage=' . $new_stage);
			// Update products only for non-ordered baskets
			if (!(isset($basket['STATUS_ID']) && $basket['STATUS_ID'] === ForgottenBasket::STATUS_ORDERED)) {
				self::updateDealProducts($deal_id, $basket, $deal_info);
			}
			return true;
		}

		// Process event handlers
		foreach (GetModuleEvents(Integration::MODULE_ID, "OnBeforeFBasketDealUpdate", true) as $event) {
			$fields = ExecuteModuleEventEx($event, [$fields, $basket, $deal_info, $profile]);
		}

		\SProdIntegration::Log('(FBasketSync::updateDealFromBasket) updating deal ' . $deal_id . ' with fields: ' . print_r($fields, true));

		try {
			$result = Rest::execute('crm.deal.update', [
				'id' => $deal_id,
				'fields' => $fields
			]);

			if ($result) {
				// Update products only for non-ordered baskets
				if (!(isset($basket['STATUS_ID']) && $basket['STATUS_ID'] === ForgottenBasket::STATUS_ORDERED)) {
					self::updateDealProducts($deal_id, $basket, $deal_info);
				}

				// Process event handlers
				foreach (GetModuleEvents(Integration::MODULE_ID, "OnAfterFBasketDealUpdate", true) as $event) {
					ExecuteModuleEventEx($event, [$deal_id, $basket, $profile]);
				}

				\SProdIntegration::Log('(FBasketSync::updateDealFromBasket) deal ' . $deal_id . ' updated');
			}
		} catch (\Exception $e) {
			\SProdIntegration::Log('(FBasketSync::updateDealFromBasket) error updating deal: ' . $e->getMessage());
			return false;
		}

		return true;
	}


	/**
	 * Get deal fields from basket
	 */
	public static function getDealFields($basket, $profile, $deal_info, $is_new = false) {
		$fields = [];
		$basket_id = $basket['ID'];

		if (!$is_new) {
			// For existing deals - update only changed fiel
			$deal = $deal_info['deal'];
		}

		// Check if stage needs to be updated based on basket status
		$current_stage = $deal['STAGE_ID'] ?? '';
		$new_stage = self::getStageForBasketStatus($basket, $profile);
		if ($new_stage && $current_stage !== $new_stage) {
			$fields['STAGE_ID'] = $new_stage;
		}

		// For ordered baskets
		if (!empty($fields) && $fields['STAGE_ID'] === ForgottenBasket::STATUS_ORDERED) {
			return $fields;
		}

		// For new deals
		if ($is_new) {
			$category_id = (int) $profile['options']['deal_category'];
			$title_prefix = ($profile['options']['fbasket_deal_title_prefix'] ?? '') ?: Loc::getMessage('SP_CI_FBASKET_DEAL_TITLE_DEFAULT');
			
			$fields['TITLE'] = $title_prefix . ' ' . $basket_id;
			$fields['CATEGORY_ID'] = $category_id;
			
			// Get currency from basket items or use default
			$currency = 'RUB';
			if (!empty($basket['ITEMS']) && isset($basket['ITEMS'][0]['CURRENCY'])) {
				$currency = $basket['ITEMS'][0]['CURRENCY'];
			}
			$fields['CURRENCY_ID'] = $currency;
			
			// Store basket ID in custom field
			$basket_id_field = self::getBasketIdField();
			$fields[$basket_id_field] = $basket_id;

			// Set source
			$source_id = Settings::get("source_id");
			if ($source_id) {
				$fields['ORIGINATOR_ID'] = $source_id;
			}

			// Set deal source
			if (!empty($profile['options']['deal_source'])) {
				$fields['SOURCE_ID'] = $profile['options']['deal_source'];
			}

			// Set responsible user
			$responsible_id = (int) $profile['options']['deal_respons_def'];
			if ($responsible_id) {
				$fields['ASSIGNED_BY_ID'] = $responsible_id;
			}

		}

		// Create or link contact: for new deals — once; for existing — only if contact actually changed
		if ($basket['USER_ID']) {
			if ($is_new) {
				$contact_id = FbasketContacts::getOrCreateContact($basket, $profile, true);
				if ($contact_id) {
					$fields['CONTACT_ID'] = $contact_id;
				}
			} else {
				$existing_contact_id = $deal['CONTACT_ID'] ?? null;
				$contact_id = FbasketContacts::getOrCreateContact($basket, $profile, false, $existing_contact_id);
				if ($contact_id) {
					$fields['CONTACT_ID'] = $contact_id;
				}
			}
		}

		// Add custom fields mapping from profile
		if (!empty($profile['fields']['comp_table'])) {
			foreach ($profile['fields']['comp_table'] as $basket_field => $sync_params) {
				if (is_array($sync_params) && ($sync_params['direction'] == 'all' || $sync_params['direction'] == 'stoc')) {
					$deal_field = $sync_params['value'];
					$basket_value = self::getBasketFieldValue($basket, $basket_field);

					if ($basket_value !== null) {
						$fields[$deal_field] = $basket_value;
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * Get basket field value
	 */
	protected static function getBasketFieldValue($basket, $field_code) {
		switch ($field_code) {
			case 'FUSER_ID':
				return $basket['FUSER_ID'];
			case 'USER_ID':
				return $basket['USER_ID'];
			case 'SITE_ID':
				return $basket['SITE_ID'];
			case 'DATE_UPDATE':
				return date('Y-m-d\TH:i:s', $basket['DATE_UPDATE']);
			case 'DATE_INSERT':
				return date('Y-m-d\TH:i:s', $basket['DATE_INSERT']);
			case 'PRICE_SUM':
				return (float) $basket['PRICE_SUM'];
			case 'ITEMS_COUNT':
				return count($basket['ITEMS']);
			default:
				return null;
		}
	}

	/**
	 * Get stage for basket status
	 */
	protected static function getStageForBasketStatus($basket, $profile) {
		$status = $basket['STATUS_ID'] ?? '';

		// Use statuses from profile configuration: comp_table[status] = array of stage ids (or legacy ['stages' => array])
		$status_table = (array) ($profile['statuses']['comp_table'] ?? []);

		if (isset($status_table[$status])) {
			$raw = $status_table[$status];
			$list = is_array($raw) && isset($raw['stages']) ? $raw['stages'] : (is_array($raw) ? $raw : []);
			$stages = array_filter($list, function($stage) {
				return $stage !== '' && $stage !== null;
			});
			if (!empty($stages)) {
				return reset($stages);
			}
		}

		// Fallback to initial stage if status not found in configuration
		return $profile['options']['fbasket_initial_stage'] ?? '';
	}

	/**
	 * Update deal products from basket items
	 */
	protected static function updateDealProducts($deal_id, $basket, $deal_info) {
		if (empty($basket['ITEMS'])) {
			\SProdIntegration::Log('(FBasketSync::updateDealProducts) no items in basket for deal ' . $deal_id);
			return false;
		}

		// Prepare order_data structure for SyncDeal::updateProducts
		$order_data = [
			'PRODUCTS' => [],
			'DELIVERY_TYPE_ID' => 0,
			'DELIVERY_PRICE' => 0,
			'DELIVERY_TYPE' => null,
		];

		// Convert basket items to order_data['PRODUCTS'] format
		foreach ($basket['ITEMS'] as $item) {
			$order_data['PRODUCTS'][] = [
				'PRODUCT_ID' => $item['PRODUCT_ID'] ?? 0,
				'PRODUCT_NAME' => Utilities::convEncForDeal($item['NAME']),
				'PRICE' => (float) $item['PRICE'],
				'QUANTITY' => (float) $item['QUANTITY'],
				'DISCOUNT_SUM' => (float) ($item['DISCOUNT_PRICE'] ?? 0),
				'VAT_RATE' => (float) ($item['VAT_RATE'] ?? 0),
				'VAT_INCLUDED' => $item['VAT_INCLUDED'] ?? 'Y',
				'MEASURE_CODE' => $item['MEASURE_CODE'] ?? null,
				'MEASURE_NAME' => $item['MEASURE_NAME'] ?? null,
			];
		}

		\SProdIntegration::Log('(FBasketSync::updateDealProducts) using SyncDeal::updateProducts for deal ' . $deal_id);

		// Use SyncDeal::updateProducts to handle product synchronization
		return SyncDeal::updateProducts($deal_id, $order_data, $deal_info);
	}
}
