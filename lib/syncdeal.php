<?php
/**
 * Synchronization from order to deal
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

class SyncDeal
{
    const MODULE_ID = 'sproduction.integration';
	const PRODUCTS_COMPARABLE_FIELDS = ['PRICE', 'PRODUCT_NAME', 'PRODUCT_ID', 'QUANTITY', 'DISCOUNT_SUM', 'TAX_RATE', 'TAX_INCLUDED', 'MEASURE_CODE'];
	const PRODUCTS_FIELDS_NUM = ['PRODUCT_ID', 'PRICE', 'QUANTITY', 'DISCOUNT_SUM', 'TAX_RATE', 'MEASURE_CODE'];
	const MODE_NORMAL = 'n';
	const MODE_AFTER_ADD = 'a';

	/**
	 * Sync order with deal
	 */
	public static function runSync(array $order_data, bool $create_access = true, $mode=self::MODE_NORMAL) {
		// Check order data
		if (empty($order_data)) {
			\SProdIntegration::Log('(SyncDeal::runSync) empty order data');
			return false;
		}
		// Check base synchronization terms
		if (!\SProdIntegration::isSyncAllow($order_data['DATE_INSERT'])) {
			\SProdIntegration::Log('(SyncDeal::runSync) sync not allowed by date');
			return false;
		}
		// Get profile
		$profile = ProfilesTable::getOrderProfile($order_data);
		if ( ! $profile) {
			\SProdIntegration::Log('(SyncDeal::runSync) profile not found');
			return false;
		}
		// Get deal
		$order_id = $order_data['ID'];
		if (Settings::getSyncMode() == Settings::SYNC_MODE_BOX) {
			$deal_id = BoxMode::findDealByOrder($order_id);
		}
		else {
			$deal_id = PortalData::findDeal($order_data, $profile);
		}
		// Wait while deal changes are fixed in order
		DealLastChanges::wait($deal_id);
		// Lock the order for other processes
		OrderProcessLock::set($order_data['ID']);
		// Update fields of the deal
		$success = false;
		if ($deal_id) {
			if ($mode == self::MODE_NORMAL) {
				OrderAddLock::delete($order_id);
				// Check if deal is changing of other process
				DealProcessLock::wait($deal_id);
				DealProcessLock::set($deal_id);
				// Get fresh deal info
				$deal_info = PortalData::getDealInfo($profile, $deal_id);
				\SProdIntegration::Log('(SyncDeal::runSync) deal ' . print_r($deal_info['deal'], true));
				$order      = Sale\Order::load($order_id);
				$order_data = StoreData::getOrderInfo($order);
				\SProdIntegration::Log('(SyncDeal::runSync) order_data ' . print_r($order_data, true));
				// Check exclusions
				$skip_sync = false;
				if (Settings::get("direction") == 'full') {
					// If changes came from deal
					if (
						Settings::getSyncMode() == Settings::SYNC_MODE_BOX 
						&& SyncDealControl::dealChangedBeforeOrder($order_data, $deal_info['deal'])
						&& !\SProduction\Integration\FieldUpdateLock::hasSavedFields($deal_info['deal']['ID'], 'deal')
					) {
						$skip_sync = true;
						\SProdIntegration::Log('(SyncDeal::runSync) order was changed from deal and no field locks set: skip sync');
					}
				}
				// Run sync
				if (!$skip_sync) {
					$deal_new_fields = self::getChangedFields($order_data, $deal_info, $profile);
					// Update contact
					try {
						$contact_id = CrmContact::syncOrderToDealContact($order_data, $deal_info, $profile);
						if ($deal_info['deal']['CONTACT_ID'] != $contact_id) {
							$deal_new_fields['CONTACT_ID'] = $contact_id;
						}
					} catch (\Exception $e) {
						\SProdIntegration::Log('(SyncDeal::runSync) can\'t sync of contact');
					}
					// Link company
					if (!$deal_info['deal']['COMPANY_ID']) {
						$company_id = CrmCompany::runSync($order_data, $profile);
						if ($company_id) {
							$deal_new_fields['COMPANY_ID'] = $company_id;
						}
					}
					// Process the event handlers
					foreach (GetModuleEvents(Integration::MODULE_ID, "OnBeforeDealUpdate", true) as $event) {
						$deal_new_fields = ExecuteModuleEventEx($event, [$deal_new_fields, $order_data, $deal_info]);
					}
					// Update deal
					$updated = self::updateFields($deal_id, $order_id, $deal_new_fields);
					// Update field hashes after successful sync (Order -> Deal)
					if ($updated) {
						SyncHashManager::updateHashesAfterSync($order_id, 'order', $deal_id, 'deal', $order_data);
					}
				}
				DealProcessLock::remove($deal_id);
				$success = true;
			}
			elseif ($mode == self::MODE_AFTER_ADD) {
				DealProcessLock::wait($deal_id);
				DealProcessLock::set($deal_id);
				$deal_info = PortalData::getDealInfo($profile, $deal_id);
				$deal_new_fields = self::getChangedFields($order_data, $deal_info, $profile, true);
				// Add contact
				try {
					$contact_id = CrmContact::syncOrderToDealContact($order_data, $deal_info, $profile);
					if ($deal_info['deal']['CONTACT_ID'] != $contact_id) {
						$deal_new_fields['CONTACT_ID'] = $contact_id;
					}
				} catch (\Exception $e) {
					\SProdIntegration::Log('(SyncDeal::runSync) can\'t sync of contact');
				}
				// Add company
				$company_id = CrmCompany::runSync($order_data, $profile);
				if ($company_id) {
					$deal_new_fields['COMPANY_ID'] = $company_id;
				}
				// Update created deal
				self::updateFields($deal_id, $order_id, $deal_new_fields);
				DealProcessLock::remove($deal_id);
				$success = true;
			}
		} // Create a new deal
		elseif ($create_access) {
			if (Settings::getSyncMode() == Settings::SYNC_MODE_NORMAL && ($mode != self::MODE_NORMAL || OrderAddLock::check($order_id, true))) {
				// Check if deal of order doesn't exist on other categories
				if (!PortalData::findDeal($order_data, $profile, true)) {
					\SProdIntegration::Log('(SyncDeal::runSync) new order data ' . print_r($order_data, 1));
					$deal_info = PortalData::getDealInfo($profile);
					$deal_fields = self::getChangedFields($order_data, $deal_info, $profile, true);
					// Add contact
					try {
						$contact_id = CrmContact::syncOrderToDealContact($order_data, $deal_info, $profile);
						if ($contact_id) {
							$deal_fields['CONTACT_ID'] = $contact_id;
						}
					} catch (\Exception $e) {
						\SProdIntegration::Log('(SyncDeal::runSync) can\'t sync of contact');
					}
					// Add company
					$company_id = CrmCompany::runSync($order_data, $profile, $contact_id);
					if ($company_id) {
						$deal_fields['COMPANY_ID'] = $company_id;
					}
					// Create a new deal
					$deal = self::createFromOrder($order_data, $deal_info, $deal_fields, $profile);
					// Process after creating
					$deal_id = $deal['ID'];
					if ($deal_id) {
						$success = true;
						if ( ! $deal_fields['CONTACT_ID']) {
							CrmNotif::sendMsg(Loc::getMessage("SP_CI_SYNC_CONTACT_ADD_ERROR_UNKNOWN"), 'ERRCNTADDUNK', CrmNotif::TYPE_ERROR, $deal_id);
						}
						$deal_info = PortalData::getDealInfo($profile, $deal_id);
						$opt_direction = Settings::get("direction");
						if ( ! $opt_direction || $opt_direction == 'full' || $opt_direction == 'ctos') {
							StoreOrder::updateByNewDeal($deal, $order_data, $deal_info, $profile);
						}
					}
				}
			}
		}
		if ($success) {
			// Update products
			if (Settings::getSyncMode() == Settings::SYNC_MODE_NORMAL) {
				self::updateProducts($deal_id, $order_data, $deal_info);
			}
			// Process the event handlers
			foreach (GetModuleEvents(Integration::MODULE_ID, "OnAfterDealProcessed", true) as $event) {
				ExecuteModuleEventEx($event, [$deal_id]);
			}
		}
		// Unlock order
		OrderProcessLock::remove($order_data['ID']);
	}

	/**
	 * Updating of deal data
	 */
	public static function updateFields($deal_id, $order_id, $d_new_fields) {
		// Check the changes for each field individually
		$fields_to_update = [];
		if (!empty($d_new_fields)) {
			foreach ($d_new_fields as $field => $value) {
				if ($value === null) {
					$value = '';
				}
				$fields_to_update[$field] = $value;
			}

			// Exclude immutable fields
			if (isset($fields_to_update['ID'])) {
				unset($fields_to_update['ID']);
			}

			if (!empty($fields_to_update)) {
				// Save time of change for handler pause
				Settings::save('deals_last_change_ts', time());
				// Send the changes
				\SProdIntegration::Log('(SyncDeal::updateFields) deal ' . $deal_id . ' update fields ' . print_r($fields_to_update, true));
				Rest::execute('crm.deal.update', [
					'id'     => $deal_id,
					'fields' => $fields_to_update,
				]);
				return true; // Indicate successful update
			} else {
				\SProdIntegration::Log('(SyncDeal::updateFields) no different fields');
			}
		} else {
			\SProdIntegration::Log('(SyncDeal::updateFields) no different fields');
		}
		return false; // No updates performed
	}

	/**
	 * Sync goods and delivery
	 */
	public static function updateProducts($deal_id, $order_data, $deal_info) {
		$result = false;
		if ( ! Rest::checkConnection()) {
			return $result;
		}
		// Check of order changes
		if (Settings::get('send_products_anyway') != 'Y' && ! Integration::isBulkRun() &&
			! UpdateLock::isChanged($deal_id, 'basket_stoc', array_merge($order_data['PRODUCTS'], [$order_data['DELIVERY_TYPE_ID'], $order_data['DELIVERY_PRICE']]), true)) {
			return $result;
		}
		// --- DEAL PRODUCTS LIST ---
		$new_rows = [];
		// Products list of deal
		foreach ($order_data['PRODUCTS'] as $item) {
			// Discount
			$price = $item['PRICE'];
			$discount_rate = 0;
			$discount_sum = $item['DISCOUNT_SUM'];
			if ($price + $discount_sum) {
				$discount_rate = round($discount_sum / ($price + $discount_sum) * 100, 4);
			}
			// Product fields
			$tax_value = 0;
			if ($item['VAT_INCLUDED'] == 'N') {
				$tax_value = $price * 0.01 * (int) $item['VAT_RATE'];
			}
			$deal_prod = [
				'PRODUCT_NAME'     => $item['PRODUCT_NAME'],
				'QUANTITY'         => $item['QUANTITY'],
				'PRICE'            => $price + $tax_value,
				'PRICE_EXCLUSIVE'  => $price,
//				'PRICE_BRUTTO'     => $price + $tax_value + $discount,
				'TAX_RATE'         => $item['VAT_RATE'],
				'TAX_INCLUDED'     => $item['VAT_INCLUDED'],
			];
			if (Settings::get('products_no_discounts')) {
				$deal_prod['DISCOUNT_TYPE_ID'] = 1;
				$deal_prod['DISCOUNT_SUM'] = 0;
			}
			else {
				if (!Settings::get('products_discounts_perc')) {
					$deal_prod['DISCOUNT_TYPE_ID'] = 1;
					$deal_prod['DISCOUNT_SUM'] = $discount_sum;
				} else {
					$deal_prod['DISCOUNT_TYPE_ID'] = 2;
					$deal_prod['DISCOUNT_RATE'] = $discount_rate;
				}
			}
			if ($item['MEASURE_CODE']) {
				$deal_prod['MEASURE_CODE'] = $item['MEASURE_CODE'];
			}
			if ($item['MEASURE_NAME']) {
				$deal_prod['MEASURE_NAME'] = $item['MEASURE_NAME'];
			}
			$new_rows[] = $deal_prod;
		}
		// --- DELIVERY ---
		$delivery_sync_type = Settings::get('products_delivery');
		if ( ! $delivery_sync_type || ($delivery_sync_type == 'notnull' && $order_data['DELIVERY_PRICE'])) {
			$deliv_row = [
				'PRODUCT_NAME' => Loc::getMessage("SP_CI_PRODUCTS_DELIVERY"),
				'PRICE'        => $order_data['DELIVERY_PRICE'],
				'QUANTITY'     => 1,
				'MEASURE_CODE' => Integration::DEAL_MEASURE_CODE_DEF,
				'PRODUCT_ID' => 0,
			];
			if ($order_data['DELIVERY_TYPE']) {
				$deliv_row['PRODUCT_NAME'] = Loc::getMessage("SP_CI_PRODUCTS_DELIVERY") . ': ' . $order_data['DELIVERY_TYPE'];
			}
			// Find delivery product
			if (Settings::get('products_deliv_prod_active')) {
				$deliv_prods = (array) Settings::get('products_deliv_prod_list', true);
				if ($order_data['DELIVERY_TYPE_ID'] && $deliv_prods[$order_data['DELIVERY_TYPE_ID']]) {
					$product_id = $deliv_prods[$order_data['DELIVERY_TYPE_ID']];
					$deliv_row['PRODUCT_ID'] = $product_id;
					if (Settings::get('products_deliv_prod_ttlprod')) {
						$product = CrmProducts::get($product_id);
						if (isset($product['NAME'])) {
							$deliv_row['PRODUCT_NAME'] = $product['NAME'];
						}
					}
				}
			}
			// VAT
			$nds_vat = (int) Settings::get('products_delivery_vat');
			if ($nds_vat) {
				$deliv_row['TAX_RATE'] = $nds_vat;
			}
			$deliv_row['TAX_INCLUDED'] = Settings::get('products_delivery_vat_included') ? 'Y' : 'N';
			$new_rows[] = $deliv_row;
		}
		// --- DEAL PRODUCTROWS DATA ---
//		$old_prod_rows = Rest::execute('crm.deal.productrows.get', [
//			'id' => $deal_id
//		]);
		$old_prod_rows = $deal_info['products'];
		// Prepare deal data
		foreach ($old_prod_rows as $p => $product) {
			if (isset($product['TAX_RATE']) && $product['TAX_RATE'] !== '') {
				$old_prod_rows[$p]['TAX_RATE'] = (int)$product['TAX_RATE'];
			}
		}
		// --- LINKING CRM PRODUCTS ---
		$sync_type = Settings::get("products_sync_type");
		$root_section = (int) Settings::get("products_root_section");
		$deal_section_id = null;
		// Section for order products
		if ($sync_type == 'create') {
			$group_by_orders = Settings::get("products_group_by_orders");
			if ($group_by_orders) {
				$deal_section = CrmProducts::findSection($order_data['ID'], $root_section);
				if ( ! $deal_section) {
					$deal_section_id = CrmProducts::addSection($order_data['ID'], $root_section);
				} else {
					$deal_section_id = $deal_section['ID'];
				}
			}
		}
		if ($deal_section_id) {
			$parent_section = $deal_section_id;
		} else {
			$parent_section = $root_section;
		}
		// Get CRM products by order products IDs
		if ($sync_type == 'create') {
			$crm_search_ids = [];
			foreach ($order_data['PRODUCTS'] as $item) {
				$crm_search_ids[] = CrmProducts::XML_ID_PREFIX . $item['PRODUCT_ID'];
			}
			\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' search filter ' . print_r([
				'ids'     => $crm_search_ids,
				'section' => $parent_section
			], true));
			$crm_prod_list = CrmProducts::find($crm_search_ids, 'XML_ID', $parent_section);
			\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' finded products ' . print_r($crm_prod_list, true));
		} elseif ($sync_type == 'find' || $sync_type == 'mixed' || $sync_type == 'find_new') {
			// Get CRM products search IDs list
			$ib_prod_ids = [];
			foreach ($order_data['PRODUCTS'] as $item) {
				$ib_prod_ids[] = $item['PRODUCT_ID'];
			}
			$store_prods_crm_search_ids = StoreProducts::getCrmSearchIDs($ib_prod_ids);
			$crm_search_ids = array_values($store_prods_crm_search_ids);
			// Find CRM products
			if ($sync_type == 'find_new') {
				\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' search filter ' . print_r([
						'ids'     => $crm_search_ids
					], true));
				$crm_prod_list = CrmProducts::findNew($crm_search_ids);
			}
			else {
				\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' search filter ' . print_r([
						'ids'     => $crm_search_ids,
						'section' => $parent_section
					], true));
				$crm_prod_list = CrmProducts::find($crm_search_ids, false, $parent_section, true);
			}
			\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' finded products ' . print_r($crm_prod_list, true));
		}
		// Products list of deal
		foreach ($order_data['PRODUCTS'] as $k => $item) {
			// Position of product in the deal
			$k_old = false;
			foreach ($old_prod_rows as $j => $row) {
				if ($item['PRODUCT_NAME'] == $row['PRODUCT_NAME']) {
					$k_old = $j;
				}
			}
			// Link CRM products
			if ( ! Integration::isBulkRun() && $k_old && $old_prod_rows[$k_old]['PRODUCT_ID'] && Settings::get('send_products_anyway') != 'Y') {
				$new_rows[$k]['PRODUCT_ID'] = $old_prod_rows[$k_old]['PRODUCT_ID'];
				\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' product id ' . $new_rows[$k]['PRODUCT_ID'] . ' already linked');
			} else {
				if ($sync_type == 'create') {
					if ($crm_prod_list[CrmProducts::XML_ID_PREFIX . $item['PRODUCT_ID']]) {
						$crm_prod_id = $crm_prod_list[CrmProducts::XML_ID_PREFIX . $item['PRODUCT_ID']];
						$new_rows[$k]['PRODUCT_ID'] = $crm_prod_id;
						// Update product
						$fields = CrmProducts::getCRMProductFields($item['PRODUCT_ID'], $deal_info['product_fields']);
						\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' update product ' . $crm_prod_id . ' fields ' . print_r($fields, true));
						try {
							CrmProducts::update($crm_prod_id, 'XML_ID', $fields);
						} catch (\Exception $e) {}
					} else {
						// Create product
						$fields = CrmProducts::getCRMProductFields($item['PRODUCT_ID'], $deal_info['product_fields']);
						\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' new product of ib_pro ' . $item['PRODUCT_ID'] . ' fields ' . print_r($fields, true));
						try {
							$crm_prod_id = CrmProducts::add(CrmProducts::XML_ID_PREFIX . $item['PRODUCT_ID'], 'XML_ID', $fields, $parent_section);
							\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' new product id ' . $crm_prod_id);
							if ($crm_prod_id) {
								$new_rows[$k]['PRODUCT_ID'] = $crm_prod_id;
								$crm_prod_list[CrmProducts::XML_ID_PREFIX . $item['PRODUCT_ID']] = $crm_prod_id;
							}
						} catch (\Exception $e) {
							CrmNotif::sendMsg(Loc::getMessage("SP_CI_SYNC_DEAL_UPDATE_PRODUCTS_ADD_ERROR", [
								'#ERROR_MSG#' => $e->getMessage(),
								'#ERROR_CODE#' => $e->getCode(),
							]), 'ERRPRODUCTADD', CrmNotif::TYPE_ERROR, $deal_id);
						}
					}
				} elseif ($sync_type == 'find') {
					$crm_prod_id = $crm_prod_list[$store_prods_crm_search_ids[$item['PRODUCT_ID']]];
					if ($crm_prod_id) {
						$new_rows[$k]['PRODUCT_ID'] = $crm_prod_id;
					}
				} elseif ($sync_type == 'find_new') {
					$crm_prod_id = $crm_prod_list[$store_prods_crm_search_ids[$item['PRODUCT_ID']]];
					if ($crm_prod_id) {
						$new_rows[$k]['PRODUCT_ID'] = $crm_prod_id;
					}
				} elseif ($sync_type == 'mixed') {
					$search_crm_field = Settings::get("products_search_crm_field");
					if ($search_crm_field) {
						$crm_prod_id = $crm_prod_list[$store_prods_crm_search_ids[$item['PRODUCT_ID']]];
						if ($crm_prod_id) {
							$new_rows[$k]['PRODUCT_ID'] = $crm_prod_id;
							// Update product
							if ( ! Settings::get('products_mixed_update_lock')) {
								$fields = CrmProducts::getCRMProductFields($item['PRODUCT_ID'], $deal_info['product_fields']);
								\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' update product ' . $crm_prod_id . ' fields ' . print_r($fields, true));
								try {
									CrmProducts::update($crm_prod_id, $search_crm_field, $fields);
								} catch (\Exception $e) {}
							}
						} else {
							// Create product
							$fields = CrmProducts::getCRMProductFields($item['PRODUCT_ID'], $deal_info['product_fields']);
							\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' new product of ib_pro ' . $item['PRODUCT_ID'] . ' fields ' . print_r($fields, true));
							$search_crm_value = $store_prods_crm_search_ids[$item['PRODUCT_ID']];
							if ($search_crm_value) {
								try {
									$crm_prod_id = CrmProducts::add($search_crm_value, $search_crm_field, $fields, $parent_section);
									\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' new product id ' . $crm_prod_id);
									if ($crm_prod_id) {
										$new_rows[$k]['PRODUCT_ID'] = $crm_prod_id;
										$crm_prod_list[$item['PRODUCT_ID']] = $crm_prod_id;
									}
								} catch (\Exception $e) {
									CrmNotif::sendMsg(Loc::getMessage("SP_CI_SYNC_DEAL_UPDATE_PRODUCTS_ADD_ERROR", [
										'#ERROR_MSG#' => $e->getMessage(),
										'#ERROR_CODE#' => $e->getCode(),
									]), 'ERRPRODUCTADD', CrmNotif::TYPE_ERROR, $deal_id);
								}
							}
						}
					}
				}
			}
		}
		\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' current deal products ' . print_r($old_prod_rows, true));
		\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' current order products ' . print_r($new_rows, true));
		// Check changes
		$new_rows = Utilities::convEncForDeal($new_rows);
		$has_changes = self::isProdrowsDifferent($old_prod_rows, $new_rows);
		// Send request
		if ($has_changes) {
			\SProdIntegration::Log('(SyncDeal::updateDealProducts) deal ' . $deal_id . ' changed products ' . print_r($new_rows, true));
			$resp = Rest::execute('crm.deal.productrows.set', [
				'id'   => $deal_id,
				'rows' => $new_rows
			]);
			// Process the event handlers
			foreach (GetModuleEvents(Integration::MODULE_ID, "OnAfterProductrowsSet", true) as $event) {
				ExecuteModuleEventEx($event, [$deal_id, $new_rows]);
			}
			if ($resp) {
				$result = true;
			}
		}

		return $result;
	}

	/**
	 * Compare product rows
	 */
	public static function isProdrowsDifferent($rows_1, $rows_2) {
		// Compare row to row by hash
		$has_changes = false;
		if (count($rows_2) != count($rows_1)) {
			$has_changes = true;
			\SProdIntegration::Log('(isProdrowsDifferent) different count');
		} else {
			$hash2 = [];
			foreach ($rows_2 as $i => $row2) {
				$row2f = [];
				foreach ($row2 as $f_code => $f_value) {
					if (in_array($f_code, self::PRODUCTS_COMPARABLE_FIELDS)
						&& ($f_code != 'PRODUCT_NAME' || !isset($row2['PRODUCT_ID']))) {
						$value = $f_value;
						if (in_array($f_code, self::PRODUCTS_FIELDS_NUM)) {
							$value = strval(round($value, 4));
						}
						$row2f[$f_code] = $value;
					}
				}
//				\SProdIntegration::Log('(isProdrowsDifferent) order flat row i' . $i . ' ' . print_r($row2f, true));
//				\SProdIntegration::Log('(isProdrowsDifferent) order encoded row i' . $i . ' ' . json_encode($row2f));
				$hash2[] = md5(json_encode($row2f));
			}
			foreach ($hash2 as $j => $h2row) {
				$hash1 = [];
				foreach ($rows_1 as $i => $row1) {
					$row2 = $rows_2[$j];
					$row1f = [];
					// Get array with same set of fields
					foreach ($row2 as $f_code => $f_value) {
						if (in_array($f_code, self::PRODUCTS_COMPARABLE_FIELDS)
							&& ($f_code != 'PRODUCT_NAME' || !isset($row1['PRODUCT_ID']))) {
							$value = $row1[$f_code];
							if (in_array($f_code, self::PRODUCTS_FIELDS_NUM)) {
								$value = strval(round($value, 4));
							}
							$row1f[$f_code] = $value;
						}
					}
//					if ($j == 0) {
//						\SProdIntegration::Log('(isProdrowsDifferent) deal flat row i' . $i . ' ' . print_r($row1f, true));
//						\SProdIntegration::Log('(isProdrowsDifferent) deal encoded row i' . $i . ' ' . json_encode($row1f));
//					}
					$hash1[] = md5(json_encode($row1f));
				}
				if (array_search($h2row, $hash1) === false) {
					$has_changes = true;
					\SProdIntegration::Log('(isProdrowsDifferent) differences in the order products item index ' . $j);
					break;
				}
			}
		}
		return $has_changes;
	}

	/**
	 * Create new deal from order
	 */
	public static function createFromOrder($order_data, $deal_info, $fields, $profile) {
		$deal = [];
		if (Rest::checkConnection()) {
			$order_id = $order_data['ID'];
			// Process the event handlers
			foreach (GetModuleEvents(Integration::MODULE_ID, "OnBeforeDealAdd", true) as $event) {
				$fields = ExecuteModuleEventEx($event, [$fields, $order_data, $deal_info]);
			}
			// Create deal
			$deal_id = false;
			\SProdIntegration::Log('(createDealFromOrder) crm.deal.add for order ' . $order_id . ': ' . print_r($fields, true));
			if (is_array($fields) && !empty($fields)) {
				$i = 0;
				do {
					if ($i) {
						usleep(100000);
					}
					$deal_id = PortalData::findDeal($order_data, $profile, true, 1);
					if ( ! $deal_id) {
						$deal_id = Rest::execute('crm.deal.add', ['fields' => $fields]);
					}
					$i ++;
				} while ( ! $deal_id && $i < 3);
			}
			if ($deal_id) {
				// Return deal details
				$deals = PortalData::getDeal([$deal_id]);
				$deal = $deals[0];
				\SProdIntegration::Log('(createDealFromOrder) order ' . $order_id . ' deal ' . $deal_id . ' created');
			} else {
				\SProdIntegration::Log('(createDealFromOrder) error: order ' . $order_id . ' deal not created');
				CrmNotif::sendMsg(Loc::getMessage("SP_CI_SYNC_DEAL_ADD_ERROR"), 'ERRDEALADD', CrmNotif::TYPE_ERROR);
			}
		}

		return $deal;
	}

	/**
	 * Get changed fields of the deal
	 */
	public static function getChangedFields(array $order_data, array $deal_info, $profile, $new_deal=false) {
		$d_new_fields = [];
		$deal = $deal_info['deal'];
		// Fields for new deal
		if ($new_deal) {
			$order_id = $order_data['ID'];
			$category_id = (int) $profile['options']['deal_category'];
			$deal_title = self::getOrdTitleWithPrefix($order_data, $profile);
			$d_new_fields = [
				'TITLE'                     => $deal_title,
				Settings::getOrderIDField() => $order_id,
				'CATEGORY_ID'               => $category_id,
				'CURRENCY_ID'               => $order_data['CURRENCY'],
				'OPPORTUNITY'               => $order_data['PRICE'],
			];
			// Source of deal
			$source_id = Settings::get("source_id");
			if ($source_id) {
				$d_new_fields['ORIGINATOR_ID'] = $source_id;
			}
			// Responsible user
			if ( ! $d_new_fields['ASSIGNED_BY_ID']) {
				$responsible_id = (int) $profile['options']['deal_respons_def'];
			}
			if ($responsible_id) {
				$d_new_fields['ASSIGNED_BY_ID'] = $responsible_id;
			}
			// Deal source
			if ($profile['options']['deal_source']) {
				$d_new_fields['SOURCE_ID'] = $profile['options']['deal_source'];
			}
		}
		// Changes of status
		$d_new_fields = array_merge($d_new_fields, self::getChangedStatus($order_data, $deal_info, $profile));
		// Changes of props
		$d_new_fields = array_merge($d_new_fields, self::getChangedProps($order_data, $deal_info, $profile));
		// Changes of other order data
		$d_new_fields = array_merge($d_new_fields, self::getChangedOther($order_data, $deal_info, $profile));
		// Changes of responsible
		$d_new_fields = array_merge($d_new_fields, self::getChangedBasic($order_data, $deal_info, $profile));

		return $d_new_fields;
	}

	/**
	 * Information of other order fields changes
	 */
	public static function getChangedOther(array $order_data, array $deal_info, $profile) {
		$changed_fields = [];
		$deal = $deal_info['deal'];
		$deal_fields = $deal_info['fields'];
		if ($deal_fields) {
			$comp_table = (array) $profile['other']['comp_table'];
			foreach ($comp_table as $o_prop_id => $sync_params) {
				$deal_code = $sync_params['value'];
				$deal_value = $deal[$deal_code] ?? false;
				if (isset($deal_fields[$deal_code]) && $deal_fields[$deal_code] && ($sync_params['direction'] == 'all' || $sync_params['direction'] == 'stoc')) {
					$new_value = [];
					// Order prop id
					$o_prop_id_cmp = $o_prop_id;
					$cmp_props = [
						'ORDER_ID'          => 'ID',
						'ORDER_NUMBER'      => 'ACCOUNT_NUMBER',
						'USER_TYPE'         => 'PERSON_TYPE_NAME',
						'DATE_CREATE'       => 'DATE_INSERT',
						'ORDER_LINK'        => 'ID',
						'ORDER_LINK_PUBLIC' => 'PUBLIC_LINK',
						'DELIV_TYPE'        => 'DELIVERY_TYPE',
						'PAY_STATUS'        => 'IS_PAID',
						'PAY_DATE'          => 'PAYMENT_DATE',
						'PAY_NUM'           => 'PAYMENT_NUM',
						'PAY_SUM'           => 'PAYMENT_SUM',
						'PAY_FACT'          => 'PAYMENT_FACT',
						'PAY_LEFT'          => 'PAYMENT_LEFT',
						'COUPON'            => 'COUPONS',
						'DELIV_TRACKNUM'    => 'TRACKING_NUMBER',
						'ORDER_STATUS'      => 'STATUS_ID',
						'ORDER_STATUS_NAME' => 'STATUS_NAME',
						'DELIVERY_STORE'    => 'STORE_NAME',
					];
					if (isset($cmp_props[$o_prop_id])) {
						$o_prop_id_cmp = $cmp_props[$o_prop_id];
					}
					// Process
					switch ($o_prop_id) {
						// Number
						case 'ORDER_ID':
						case 'ORDER_NUMBER':
						case 'USER_ID':
						case 'DELIVERY_PRICE':
						case 'DELIVERY_PRICE_CALCULATE':
						case 'PAY_SUM':
						case 'PAY_FACT':
						case 'PAY_LEFT':
							$value = $order_data[$o_prop_id_cmp];
							$new_value[] = $value;
							break;
						// String
						case 'USER_TYPE':
						case 'ORDER_LINK_PUBLIC':
						case 'USER_DESCRIPTION':
						case 'USER_NAME':
						case 'USER_LAST_NAME':
						case 'USER_SECOND_NAME':
						case 'USER_EMAIL':
						case 'USER_PHONE':
						case 'PAY_NUM':
						case 'DELIV_TRACKNUM':
							$value = $order_data[$o_prop_id_cmp];
							$new_value[] = Utilities::convEncForDeal($value);
							break;
						// String or list
						case 'SITE_ID':
						case 'SITE_CODE':
						case 'DELIV_TYPE':
						case 'ORDER_STATUS':
						case 'PAY_TYPE':
						case 'PAY_ID':
						case 'ORDER_STATUS_NAME':
						case 'DELIVERY_STATUS':
						case 'DELIVERY_STATUS_NAME':
						case 'DELIVERY_COMPANY_NAME':
						case 'DELIVERY_STORE':
							if ($order_data[$o_prop_id_cmp]) {
								$store_value = $order_data[$o_prop_id_cmp];
								if ( ! empty($deal_fields[$deal_code]['items'])) {
									foreach ($deal_fields[$deal_code]['items'] as $deal_f_value) {
										if (trim($deal_f_value['VALUE']) == trim(Utilities::convEncForDeal($store_value))) {
											$new_value[] = $deal_f_value['ID'];
										}
									}
								} else {
									$new_value[] = Utilities::convEncForDeal($store_value);
								}
							} else {
								$new_value[] = false;
							}
							break;
						// Date
						case 'DATE_CREATE':
							if ($deal_fields[$deal_code]['type'] == 'date') {
								$value = date(ProfileInfo::DATE_FORMAT_PORTAL_SHORT, $order_data[$o_prop_id_cmp]);
								$deal_value = date(ProfileInfo::DATE_FORMAT_PORTAL_SHORT, strtotime($deal_value));
							} else {
								$value = date(ProfileInfo::DATE_FORMAT_PORTAL, $order_data[$o_prop_id_cmp]);
								$deal_value = date(ProfileInfo::DATE_FORMAT_PORTAL, strtotime($deal_value));
							}
							$new_value[] = $value;
							break;
						case 'ORDER_LINK':
							$site_url = Settings::get("site");
							$order_link = $site_url . '/bitrix/admin/sale_order_view.php?ID=' . $order_data[$o_prop_id_cmp];
							$new_value[] = $order_link;
							break;
						// Manager comment
						case 'COMMENTS':
							$comments_val = $order_data[$o_prop_id_cmp];
							$new_value[] = Utilities::convEncForDeal($comments_val);
							break;
						case 'PAY_STATUS':
							if ($order_data[$o_prop_id_cmp]) {
								$new_value[] = true;
							} else {
								$new_value[] = false;
							}
							break;
						case 'PAY_DATE':
							if ($order_data[$o_prop_id_cmp]) {
								$value = $order_data[$o_prop_id_cmp]->format(ProfileInfo::DATE_FORMAT_PORTAL_SHORT);
								$deal_value = date(ProfileInfo::DATE_FORMAT_PORTAL_SHORT, strtotime($deal_value));
								$new_value[] = Utilities::convEncForDeal($value);
							}
							break;
						case 'COUPON':
							if ($deal_fields[$deal_code]['isMultiple']) {
								$value = $order_data['COUPONS'];
								$new_value = Utilities::convEncForDeal($value);
							}
							else {
								$value = implode(', ', $order_data['COUPONS']);
								$new_value[] = Utilities::convEncForDeal($value);
							}
							break;
						case 'DELIVERY_ALLOW':
						case 'DELIVERY_DEDUCTED':
							$new_value[] = $order_data[$o_prop_id_cmp] == 'Y' ? 1 : 0;
							break;
						// User groups IDs
						case 'USER_GROUPS_IDS':
							if (!empty($order_data[$o_prop_id_cmp])) {
								foreach ($order_data[$o_prop_id_cmp] as $group_id) {
									$new_value[] = $group_id;
								}
							}
							break;
						// User groups names
						case 'USER_GROUPS_NAMES':
							if (!empty($order_data[$o_prop_id_cmp])) {
								foreach ($order_data[$o_prop_id_cmp] as $group_name) {
									$new_value[] = Utilities::convEncForDeal($group_name);
								}
							}
							break;
					}
					$deal_value = is_array($deal_value) ? $deal_value : (! $deal_value ? [] : [$deal_value]);
					if ( ! StoreOrder::isEqual($new_value, $deal_value)) {
						// Check if order field has changed using FieldUpdateLock
						if (FieldUpdateLock::isChanged($order_data['ID'], 'order', $o_prop_id_cmp, $order_data[$o_prop_id_cmp])) {
						if ($deal_fields[$deal_code]['isMultiple']) {
							$changed_fields[$deal_code] = $new_value;
						} else {
							$changed_fields[$deal_code] = $new_value[0];
						}
					}
							// Format comment field
							if ($o_prop_id == 'COMMENTS' && $deal_code == 'COMMENTS' && isset($changed_fields[$deal_code])) {
								$changed_fields[$deal_code] = str_replace("\n",  "<br>", $changed_fields[$deal_code]);
							}
						}
				}
			}
		}

		return $changed_fields;
	}

	/**
	 * Information of properties changes
	 */
	public static function getChangedProps(array $order_data, array $deal_info, $profile) {
		$changed_fields = [];
		$deal = $deal_info['deal'];
		$deal_fields = $deal_info['fields'];
		$person_type = $order_data['PERSON_TYPE_ID'];
		$comp_table = (array) $profile['props']['comp_table'];
		foreach ($comp_table as $o_prop_id => $sync_params) {
			$d_f_code = $sync_params['value'];
			if ($deal_fields[$d_f_code] && ($sync_params['direction'] == 'all' || $sync_params['direction'] == 'stoc')) {
				$new_value = false;
				$deal_value = $deal[$d_f_code];
				// Properties
				foreach ($order_data['PROPERTIES'] as $prop) {
					$value = false;
					if ($prop['ID'] == $o_prop_id && $prop['PERSON_TYPE_ID'] == $person_type) {
//						\SProdIntegration::Log('(syncOrderToDeal) $prop: ' . print_r($prop, true));
						switch ($prop['TYPE']) {
							case 'ENUM':
								foreach ($prop['VALUE'] as $value_code) {
									foreach ($deal_fields[$d_f_code]['items'] as $deal_f_value) {
										if ($deal_f_value['VALUE'] == Utilities::convEncForDeal($prop['OPTIONS'][$value_code])) {
											$new_value[] = $deal_f_value['ID'];
										}
									}
								}
								break;
							case 'FILE':
								foreach ($prop['VALUE'] as $file) {
									// Prepare field of order
									if ($file['ID']) {
										$path = $_SERVER['DOCUMENT_ROOT'] . $file['SRC'];
										$data = file_get_contents($path);
										$new_value[] = [
											'fileData' => [
												Utilities::convEncForDeal($file['ORIGINAL_NAME']),
												base64_encode($data)
											],
											'fileId'   => $path,
										];
									} else {
										$path = $file['tmp_name'];
										$data = file_get_contents($path);
										$new_value[] = [
											'fileData' => [
												Utilities::convEncForDeal($file['name']),
												base64_encode($data)
											],
											'fileId'   => $path,
										];
									}
								}
								if ( ! $new_value) {
									if ( ! $deal_fields[$d_f_code]['isMultiple']) {
										$d_value = $deal[$d_f_code];
										if ($d_value['id']) {
											$new_value[] = [
												'id'     => $d_value['id'],
												'remove' => 'Y',
											];
										}
									} else {
										if (!empty($deal[$d_f_code])) {
											foreach ($deal[$d_f_code] as $d_value) {
												if ($d_value['id']) {
													$new_value[] = [
														'id'     => $d_value['id'],
														'remove' => 'Y',
													];
												}
											}
										}
									}
								}
								// Prepare field of deal
								if (isset($deal_value['id'])) {
									$deal_value = [$deal_value];
								}
								break;
							case 'LOCATION':
								if ( ! is_array($prop['VALUE'])) {
									$prop['VALUE'] = array($prop['VALUE']);
								}
								foreach ($prop['VALUE'] as $p_value) {
									if ($p_value) {
										$new_value[] = Utilities::convEncForDeal(\Bitrix\Sale\Location\Admin\LocationHelper::getLocationPathDisplay($p_value));
									} else {
										$new_value[] = '';
									}
								}
								break;
							case 'Y/N':
								$new_value[] = $prop['VALUE'][0] == 'Y' ? 1 : 0;
								break;
							case 'DATE':
								if ($deal_fields[$d_f_code]['type'] == 'date') {
									$value[] = date(ProfileInfo::DATE_FORMAT_PORTAL_SHORT, strtotime($prop['VALUE'][0]));
									$deal_value = date(ProfileInfo::DATE_FORMAT_PORTAL_SHORT, strtotime($deal_value));
								} else {
									$value[] = date(ProfileInfo::DATE_FORMAT_PORTAL, strtotime($prop['VALUE'][0]));
									$deal_value = date(ProfileInfo::DATE_FORMAT_PORTAL, strtotime($deal_value));
								}
								$new_value = Utilities::convEncForDeal($value);
								break;
							default:
								if (is_array($prop['VALUE']) && count($prop['VALUE']) === 1 && ! $prop['VALUE'][0]) {
									$prop['VALUE'] = [];
								}
								$new_value = Utilities::convEncForDeal($prop['VALUE']);
						}
						break;
					}
				}

				if ($new_value !== false) {
					//\SProdIntegration::Log('(syncOrderToDeal) new_value: ' . print_r($new_value, true));
					//\SProdIntegration::Log('(syncOrderToDeal) deal_value: ' . print_r($deal_value, true));
					$deal_value = is_array($deal_value) ? $deal_value : (! $deal_value ? [] : [$deal_value]);
					if ( ! StoreOrder::isEqual($new_value, $deal_value, $order_data['ID'])) {
						// Check if order property has changed using FieldUpdateLock
						$prop_value = '';
						foreach ($order_data['PROPERTIES'] as $prop) {
							if ($prop['ID'] == $o_prop_id && $prop['PERSON_TYPE_ID'] == $person_type) {
								$prop_value = $prop['VALUE'];
								break;
							}
						}
						if (FieldUpdateLock::isChanged($order_data['ID'], 'order', 'PROPERTY_' . $o_prop_id, $prop_value)) {
							if ($deal_fields[$d_f_code]['isMultiple']) {
								$changed_fields[$d_f_code] = $new_value;
							} else {
								$changed_fields[$d_f_code] = $new_value[0];
							}
						}
					}
				}
			}
		}

		return $changed_fields;
	}

	/**
	 * Get changes of basic deal fields
	 */
	public static function getChangedBasic(array $order_data, array $deal_info, $profile) {
		$changed_fields = [];
		$deal = $deal_info['deal'];
		// Assigned user
		if (Settings::get('link_responsibles') && $order_data['RESPONSIBLE_ID']) {
			$new_user_id = StoreData::findCrmUser($order_data['RESPONSIBLE_ID'], $deal_info);
			if ($new_user_id && $deal['ASSIGNED_BY_ID'] != $new_user_id) {
				// Check if RESPONSIBLE_ID field has changed using FieldUpdateLock
				if (FieldUpdateLock::isChanged($order_data['ID'], 'order', 'RESPONSIBLE_ID', $order_data['RESPONSIBLE_ID'])) {
					$changed_fields['ASSIGNED_BY_ID'] = $new_user_id;
				}
			}
		}
		// Currency
		if ($order_data['CURRENCY'] && $deal['CURRENCY_ID'] != $order_data['CURRENCY']) {
			// Check if CURRENCY field has changed using FieldUpdateLock
			if (FieldUpdateLock::isChanged($order_data['ID'], 'order', 'CURRENCY', $order_data['CURRENCY'])) {
				$changed_fields['CURRENCY_ID'] = $order_data['CURRENCY'];
			}
		}

		return $changed_fields;
	}

	/**
	 * Information of status changes
	 */
	public static function getChangedStatus(array $order_data, array $deal_info, $profile) {
		$changed_fields = [];
		$status_table = (array) $profile['statuses']['comp_table'];
//		\SProdIntegration::Log('(getDealChangedStatus) status_table '.print_r($status_table, true));
		$cancel_table = (array) $profile['statuses']['cancel_stages'];
		$reverse_disable = $profile['statuses']['reverse_disable'] ? true : false;
		$deal = $deal_info['deal'];
		if (!isset($deal['CATEGORY_ID']) || $deal['CATEGORY_ID'] == $profile['options']['deal_category']) {
			$d_stage_id = $deal['STAGE_ID'] ?? '';
			$new_stage = false;
			// Change stage if set conformity of status and statuses is different
			$sync_params = $status_table[$order_data['STATUS_ID']];
			$deal_stages = (array) $sync_params['stages'];
			$deal_stages = array_diff($deal_stages, ['']);
			if ( ! empty($deal_stages) && ($sync_params['direction'] == 'all' || $sync_params['direction'] == 'stoc')) {
				if ( ! in_array($d_stage_id, $deal_stages)) {
					$new_stage = $deal_stages[0];
				}
			}
			// Stage of canceled order
			if ($order_data['IS_CANCELED'] && !empty($cancel_table)) {
				if (($new_stage && ! in_array($new_stage, $cancel_table))
					|| ( ! $new_stage && ! in_array($d_stage_id, $cancel_table))) {
					$new_stage = $cancel_table[0];
				}
			}
			// Check if is reverse stage
			if ($new_stage && $reverse_disable) {
				$stages_list = [];
				foreach ($deal_info['stages'] as $item) {
					$stages_list[$item['STATUS_ID']] = count($stages_list);
				}
				if ($stages_list[$new_stage] <= $stages_list[$deal['STAGE_ID']]) {
					$new_stage = false;
				}
			}
			if ($new_stage) {
				$status_changed = FieldUpdateLock::isChanged($order_data['ID'], 'order', 'STATUS_ID', $order_data['STATUS_ID']);
				$cancel_changed = FieldUpdateLock::isChanged($order_data['ID'], 'order', 'IS_CANCELED', $order_data['IS_CANCELED']);
				if ($status_changed || $cancel_changed) {
					$changed_fields['STAGE_ID'] = $new_stage;
				}
			}
		}
		return $changed_fields;
	}

	/**
	 * Get CRM order title
	 */
	public static function getOrdTitleWithPrefix(array $order_data, $profile) {
		$prefix = self::getPrefix($profile);
		$order_num = $order_data['ACCOUNT_NUMBER'];
		$title = $prefix . $order_num;

		return $title;
	}

	/**
	 * Get prefix option
	 */
	public static function getPrefix($profile) {
		$prefix = $profile['options']['prefix'];

		return $prefix;
	}

}
