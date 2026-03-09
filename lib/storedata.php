<?php
/**
 *    Different info from store
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

class StoreData
{
    const MODULE_ID = 'sproduction.integration';

	/**
	 * Order data
	 */
	public static function getOrderInfo($order, $changed_fields = false) {
		\SProdIntegration::Log('(StoreData::getOrderInfo)');
		$order_data = [];
		if ($order) {
			$order_data['NEW_VALUES'] = $changed_fields;
			$order_data['ID'] = $order->getId();
			$order_data['SITE_ID'] = $order->getSiteId();
			if ($order->getDateInsert()) {
				$order_data['DATE_INSERT'] = $order->getDateInsert()->getTimestamp();
			}
			$order_data['STATUS_ID'] = $order->getField('STATUS_ID');
			$res = \Bitrix\Sale\Internals\StatusLangTable::getList(array(
				'filter' => [
					'STATUS.ID' => $order_data['STATUS_ID'],
					'LID'       => LANGUAGE_ID,
				],
				'select' => ['NAME'],
			));
			if ($status_lang = $res->fetch()) {
				$order_data['STATUS_NAME'] = $status_lang['NAME'];
			}
			$order_data['PERSON_TYPE_ID'] = $order->getPersonTypeId();
			$order_data['PERSON_TYPE_NAME'] = '';
			if ($order->getPersonTypeId()) {
				$persons_types = \Bitrix\Sale\PersonType::load(false, $order->getPersonTypeId());
				$order_data['PERSON_TYPE_NAME'] = $persons_types[$order->getPersonTypeId()]['NAME'];
			}
			$order_data['USER_ID'] = $order->getUserId();
			$order_data['USER_NAME'] = '';
			$order_data['USER_LAST_NAME'] = '';
			$order_data['USER_SECOND_NAME'] = '';
			$order_data['USER_EMAIL'] = '';
			$order_data['USER_PHONE'] = '';
			if ($order->getUserId()) {
				$user_res = \CUser::GetByID($order->getUserId());
				if ($user = $user_res->Fetch()) {
					$order_data['USER_NAME'] = $user['NAME'];
					$order_data['USER_LAST_NAME'] = $user['LAST_NAME'];
					$order_data['USER_SECOND_NAME'] = $user['SECOND_NAME'];
					$order_data['USER_EMAIL'] = $user['EMAIL'];
					$order_data['USER_PHONE'] = $user['PERSONAL_PHONE'] ?: ($user['WORK_PHONE'] ?: $user['PERSONAL_MOBILE']);
				}
			}
			$order_data['USER_GROUPS_IDS'] = [];
			$order_data['USER_GROUPS_NAMES'] = [];
			$db = \Bitrix\Main\UserGroupTable::getList(array(
				'filter' => array('USER_ID' => $order->getUserId(), 'GROUP.ACTIVE' => 'Y'),
				'select' => array('GROUP_ID', 'GROUP_CODE' => 'GROUP.STRING_ID', 'GROUP_NAME' => 'GROUP.NAME'),
				'order'  => array('GROUP.C_SORT' => 'ASC'),
			));
			while ($item = $db->fetch()) {
				$order_data['USER_GROUPS_IDS'][] = $item['GROUP_ID'];
				$order_data['USER_GROUPS_NAMES'][] = $item['GROUP_NAME'];
			}
			$order_data['RESPONSIBLE_ID'] = $order->getField('RESPONSIBLE_ID');
			$order_data['PRICE'] = $order->getPrice();
			$order_data['DISCOUNT_PRICE'] = $order->getDiscountPrice();
			$order_data['DELIVERY_PRICE'] = $order->getDeliveryPrice();
			$order_data['CURRENCY'] = $order->getCurrency();
			$order_data['IS_PAID'] = $order->isPaid();
			$order_data['ID_ALLOW_DELIVERY'] = $order->isAllowDelivery();
			$order_data['IS_SHIPPED'] = $order->isShipped();
			$order_data['IS_CANCELED'] = $order->isCanceled();
			$order_data['REASON_CANCELED'] = $order->getField('REASON_CANCELED');
			$order_data['ACCOUNT_NUMBER'] = $order->getField('ACCOUNT_NUMBER');
			if ($order->getField('DATE_UPDATE')) {
				$order_data['DATE_UPDATE'] = $order->getField('DATE_UPDATE')->getTimestamp();
			}
			$order_data['COMMENTS'] = $order->getField('COMMENTS');
			$order_data['USER_DESCRIPTION'] = $order->getField('USER_DESCRIPTION');
			if ($order->getId() && \Bitrix\Sale\Helpers\Order::isAllowGuestView($order)) {
				$link = \Bitrix\Sale\Helpers\Order::getPublicLink($order);
				$order_data['PUBLIC_LINK'] = str_replace('http://', 'https://', $link);
			}
			// Properties
			$property_collection = $order->getPropertyCollection();
			$property_data = $property_collection->getArray();
			$order_data['PROPERTIES'] = [];
			foreach ($property_data['properties'] as $prop) {
				$order_data['PROPERTIES'][$prop['ID']] = $prop;
			}
			$order_data['PROP_GROUPS'] = $property_data['groups'];
			// Delivery data
			$shipment = StoreOrder::getShipment($order, true);
			if (is_object($shipment)) {
				$order_data['DELIVERY_TYPE_ID'] = $shipment->getField('DELIVERY_ID');
				$order_data['DELIVERY_TYPE'] = $shipment->getField('DELIVERY_NAME');
				$order_data['DELIVERY_STATUS'] = $shipment->getField('STATUS_ID');
				$order_data['DELIVERY_STATUS_NAME'] = '';
				$stat_res = \Bitrix\Sale\Internals\StatusLangTable::getList([
					'filter' => [
						'STATUS_ID' => $shipment->getField('STATUS_ID'),
						'LID'       => LANGUAGE_ID,
					]
				]);
				if ($item = $stat_res->fetch()) {
					$order_data['DELIVERY_STATUS_NAME'] = $item['NAME'];
				}
				$order_data['DELIVERY_ALLOW'] = $shipment->getField('ALLOW_DELIVERY');
				$order_data['DELIVERY_DEDUCTED'] = $shipment->getField('DEDUCTED');
				$order_data['TRACKING_NUMBER'] = $shipment->getField('TRACKING_NUMBER');
				$delivery_price_calc = '';
				try {
					$delivery_calc = $shipment->calculateDelivery();
					if (is_object($delivery_calc)) {
						$delivery_price_calc = $delivery_calc->getDeliveryPrice();
					}
				} catch (\Exception $e) {}
				$order_data['DELIVERY_PRICE_CALCULATE'] = $delivery_price_calc;
				$order_data['STORE_ID'] = $shipment->getStoreId();
				if ($order_data['STORE_ID']) {
					$res = \Bitrix\Catalog\StoreTable::getById($order_data['STORE_ID']);
					$store = $res->fetch();
					$order_data['STORE_NAME'] = $store['TITLE'];
				}
			}
			$order_data['DELIVERY_COMPANY_NAME'] = '';
			if ($order->getField('COMPANY_ID')) {
				$res = \Bitrix\Sale\Internals\CompanyTable::getById($order->getField('COMPANY_ID'));
				$company = $res->fetch();
				$order_data['DELIVERY_COMPANY_NAME'] = $company['NAME'];
			}
			// Payment data
//			$order_data['IS_PAID'] = false;
			$payment_collection = $order->getPaymentCollection();
			if (is_object($payment_collection->current())) {
				$order_data['PAY_TYPE'] = $payment_collection->current()->getPaymentSystemName();
				$order_data['PAY_TYPE_ID'] = $payment_collection->current()->getPaymentSystemId();
				$order_data['PAY_ID'] = $payment_collection->current()->getId();
//				if ($payment_collection->isPaid()) {
//					$order_data['IS_PAID'] = true;
//				}
			}
			$order_data['PAYMENT_NUM'] = $order->getField("PAY_VOUCHER_NUM");
			$order_data['PAYMENT_DATE'] = $order->getField("PAY_VOUCHER_DATE");
			// Paid sum
			$order_data['PAYMENT_SUM'] = $order->getPrice();
			$order_data['PAYMENT_FACT'] = $order->getSumPaid();
			$order_data['PAYMENT_LEFT'] = $order_data['PAYMENT_SUM'] - $order_data['PAYMENT_FACT'];
			// Coupons
			$discount = $order->getDiscount()->getApplyResult();
			$coupons = [];
			if ( ! empty($discount['COUPON_LIST'])) {
				foreach ($discount['COUPON_LIST'] as $coupon) {
					$coupons[] = $coupon['COUPON'];
				}
			}
			$order_data['COUPONS'] = $coupons;
			// Products (with properties)
			$product_items = [];
			if ($order->getId()) {
				$prod_res = \Bitrix\Sale\Basket::getList([
					'filter' => [
						'=ORDER_ID' => $order->getId(),
					]
				]);
				while ($item = $prod_res->fetch()) {
					$bskt_res = \Bitrix\Sale\Internals\BasketPropertyTable::getList([
						'order'  => [
							"SORT" => "ASC",
							"ID"   => "ASC"
						],
						'filter' => [
							"BASKET_ID" => $item['ID'],
						],
					]);
					$item['PROPS'] = [];
					while ($property = $bskt_res->fetch()) {
						$k = $property['CODE'] ? : $property['ID'];
						$item['PROPS'][$k] = $property['VALUE'];
					}
					$product_items[] = $item;
				}
			}
			$complects_sync_type = Settings::get('products_complects');
			$order_data['PRODUCTS'] = [];
			$processed_products = []; // Track already processed products
			$processed_complects = []; // Track already processed product sets

			foreach ($product_items as $item) {
				if ( ! $item['SET_PARENT_ID']) {
					// Check if this product has already been processed
					if (isset($processed_products[$item['PRODUCT_ID']])) {
						continue; // Skip duplicate product
					}
					$processed_products[$item['PRODUCT_ID']] = true;

					// Name of product
					$prod_name = $item['NAME'];
					$opt_prod_name_props = Settings::get("products_name_props", true);
					$opt_prod_name_props_delim = Settings::get("products_name_props_delim");
					foreach ($opt_prod_name_props as $prop_code) {
						if ($prop_code && isset($item['PROPS'][$prop_code])) {
							$prod_name .= $opt_prod_name_props_delim . $item['PROPS'][$prop_code];
						}
					}
					$order_data['PRODUCTS'][] = [
						'PRODUCT_ID'   => $item['PRODUCT_ID'],
						'PRODUCT_NAME' => $prod_name,
						'PRICE'        => $item['PRICE'],
						'BASE_PRICE'   => $item['BASE_PRICE'],
						'DISCOUNT_SUM' => $item['BASE_PRICE'] - $item['PRICE'],
						'QUANTITY'     => $item['QUANTITY'],
						'MEASURE_NAME' => $item['MEASURE_NAME'],
						'MEASURE_CODE' => $item['MEASURE_CODE'],
						'VAT_RATE'     => ($item['VAT_RATE'] === NULL ? $item['VAT_RATE'] : $item['VAT_RATE'] * 100),
						'VAT_INCLUDED' => $item['VAT_INCLUDED'],
						'PROPS'        => $item['PROPS'],
					];
					if ($complects_sync_type == 'prod') {
						foreach ($product_items as $item2) {
							if ($item2['SET_PARENT_ID'] == $item['ID']) {
								// Check if this product set has already been processed
								$complect_key = $item['PRODUCT_ID'] . '_' . $item2['PRODUCT_ID'];
								if (isset($processed_complects[$complect_key])) {
									continue; // Skip duplicate product set
								}
								$processed_complects[$complect_key] = true;

								$order_data['PRODUCTS'][] = [
									'PRODUCT_ID'   => $item2['PRODUCT_ID'],
									'PRODUCT_NAME' => $item['NAME'] . ': ' . $item2['NAME'],
									'PRICE'        => 0,
									'DISCOUNT_SUM' => 0,
									'QUANTITY'     => $item2['QUANTITY'],
									'MEASURE_NAME' => $item2['MEASURE_NAME'],
									'MEASURE_CODE' => $item2['MEASURE_CODE'],
									'VAT_RATE'     => ($item2['VAT_RATE'] === '' ? $item2['VAT_RATE'] : $item2['VAT_RATE'] * 100),
									'VAT_INCLUDED' => $item2['VAT_INCLUDED'],
								];
							}
						}
					}
				}
			}
		}

		return $order_data;
	}

	/**
	 * Find CRM user
	 */
	public static function findCrmUser($store_user_id, array $deal_info = []) {
		$crm_user_id = false;
		$res = \CUser::GetByID($store_user_id);
		$store_user = $res->Fetch();
		$user_email = $store_user['EMAIL'];
		if ($deal_info['assigned_user']['EMAIL'] && $user_email == $deal_info['assigned_user']['EMAIL']) {
			$crm_user_id = $deal_info['assigned_user']['ID'];
		} else {
			$res = Rest::execute('user.get', [
				'FILTER' => [
					'EMAIL' => $user_email,
				]
			]);
			if ($res[0]['ID']) {
				$crm_user_id = $res[0]['ID'];
			}
		}

		return $crm_user_id;
	}

}
