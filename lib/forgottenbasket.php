<?php
/**
 * Work with forgotten baskets (abandoned carts)
 *
 * @mail support@s-production.online
 * @link s-production.online
 */
namespace SProduction\Integration;

\Bitrix\Main\Loader::includeModule('sale');

use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Type;
use Bitrix\Sale\Fuser;
use Bitrix\Sale\Internals\BasketTable;
use Bitrix\Sale\Internals\FuserTable;
use Bitrix\Sale\Internals\OrderTable;

class ForgottenBasket
{
	const MODULE_ID = 'sproduction.integration';

	const STATUS_FORGOTTEN = 'forgotten';
	const STATUS_ACTIVE = 'active';
	const STATUS_ORDERED = 'ordered';

	const DATE_TYPE_UPDATE = 'DATE_UPDATE';
	const DATE_TYPE_INSERT = 'DATE_INSERT';

	/**
	 * Get list of forgotten baskets older than N hours
	 */
	public static function getListOlderThanHours($hours, $site_id = null, $limit = 0) {
		$date_to = (new Type\DateTime())->add('-' . (int) $hours . ' hours');
		return self::getList(null, $date_to, $site_id, $limit, $hours);
	}

	/**
	 * Get list of forgotten baskets older than N days (deprecated, use getListOlderThanHours)
	 */
	public static function getListOlderThanDays($days, $site_id = null, $limit = 0) {
		return self::getListOlderThanHours($days * 24, $site_id, $limit);
	}

	/**
	 * Get list of forgotten baskets in period
	 */
	public static function getList($date_from = null, $date_to = null, $site_id = null, $limit = 0, $forgotten_hours = null, $date_type = self::DATE_TYPE_UPDATE) {
		$dt_from = $date_from ? self::normalizeDate($date_from) : null;
		$dt_to = $date_to ? self::normalizeDate($date_to) : null;
		if ($dt_from && $dt_to && $dt_from->getTimestamp() > $dt_to->getTimestamp()) {
			$tmp = $dt_from;
			$dt_from = $dt_to;
			$dt_to = $tmp;
		}

		// Stage 1: Formation of elements from baskets not linked to orders
		$baskets_without_orders = self::getBasketsWithoutOrders($dt_from, $dt_to, $site_id, $limit, $date_type);

		// Stage 2: Formation of elements from completed orders taken from the orders table
		$ordered_baskets = self::getOrderedBasketsFromOrders($dt_from, $dt_to, $site_id, $limit, $date_type);

		// Stage 3: Merging into one list by ascending date
		$all_baskets = self::mergeAndSortBaskets($baskets_without_orders, $ordered_baskets, $forgotten_hours, $date_type);

		return $all_baskets;
	}

	/**
	 * Stage 1: Formation of elements from baskets not linked to orders
	 */
	private static function getBasketsWithoutOrders($dt_from, $dt_to, $site_id, $limit, $date_type) {
		$filter = [
			'=DELAY'         => 'N',
			'=CAN_BUY'       => 'Y',
			'=ORDER_ID'      => null, // Корзины без привязки к заказам
		];
		if ($dt_from) {
			$filter['>=' . $date_type] = $dt_from;
		}
		if ($dt_to) {
			$filter['<=' . $date_type] = $dt_to;
		}
		if ($site_id) {
			$filter['=LID'] = $site_id;
		}

		$params = [
			'filter' => $filter,
			'runtime' => [
				new ReferenceField(
					'FUSER',
					FuserTable::class,
					['=this.FUSER_ID' => 'ref.ID'],
					['join_type' => 'inner']
				),
			],
			'select' => [
				'ID',
				'FUSER_ID',
				'LID',
				'PRODUCT_ID',
				'NAME',
				'PRICE',
				'QUANTITY',
				'CURRENCY',
				'DATE_UPDATE',
				'DATE_INSERT',
				'DISCOUNT_PRICE',
				'CUSTOM_PRICE',
				'VAT_RATE',
				'VAT_INCLUDED',
				'ORDER_ID'
			],
			'order' => [$date_type => 'DESC'],
		];
		$params['filter']['>FUSER.USER_ID'] = 0;
		if ($limit) {
			$params['limit'] = (int) $limit;
		}

		$baskets = [];
		$res = BasketTable::getList($params);
		while ($row = $res->fetch()) {
			// Get user ID
			$user_id = 0;
			if (method_exists('\Bitrix\Sale\Fuser', 'getUserIdById')) {
				$user_id = (int) Fuser::getUserIdById($row['FUSER_ID']);
			}
			$last_order_id = self::getLastOrderId($user_id);
			// Get basket ID
			$key = (int) $row['FUSER_ID'] . '_' . $last_order_id;

			if (!isset($baskets[$key])) {
				$basket_date_update = self::formatDateValue($row['DATE_UPDATE']);
				$baskets[$key] = [
					'ID'          => $key,
					'FUSER_ID'    => (int) $row['FUSER_ID'],
					'USER_ID'     => $user_id,
					'SITE_ID'     => $row['LID'],
					'DATE_UPDATE' => $basket_date_update,
					'DATE_INSERT' => self::formatDateValue($row['DATE_INSERT']),
					'PRICE_SUM'   => 0,
					'ORDER_ID'    => 0,
					'ITEMS'       => [],
				];
			}

			$row_update_ts = self::formatDateValue($row['DATE_UPDATE']);
			if ($row_update_ts && $row_update_ts > $baskets[$key]['DATE_UPDATE']) {
				$baskets[$key]['DATE_UPDATE'] = $row_update_ts;
			}

			$row_insert_ts = self::formatDateValue($row['DATE_INSERT']);
			if ($row_insert_ts && (!$baskets[$key]['DATE_INSERT'] || $row_insert_ts < $baskets[$key]['DATE_INSERT'])) {
				$baskets[$key]['DATE_INSERT'] = $row_insert_ts;
			}

			$baskets[$key]['PRICE_SUM'] += (float) $row['PRICE'] * (float) $row['QUANTITY'];

			$baskets[$key]['ITEMS'][] = [
				'BASKET_ID'      => (int) $row['ID'],
				'PRODUCT_ID'     => (int) $row['PRODUCT_ID'],
				'NAME'           => $row['NAME'],
				'PRICE'          => $row['PRICE'],
				'QUANTITY'       => $row['QUANTITY'],
				'CURRENCY'       => $row['CURRENCY'],
				'CUSTOM_PRICE'   => $row['CUSTOM_PRICE'],
				'DISCOUNT_PRICE' => $row['DISCOUNT_PRICE'],
				'VAT_RATE'       => $row['VAT_RATE'],
				'VAT_INCLUDED'   => $row['VAT_INCLUDED'],
				'DATE_UPDATE'    => $row_update_ts,
				'DATE_INSERT'    => $row_insert_ts,
				'ORDER_ID'       => 0,
			];
		}

		return $baskets;
	}

	/**
	 * Stage 2: Formation of elements from completed orders taken from the orders table
	 */
	private static function getOrderedBasketsFromOrders($dt_from, $dt_to, $site_id, $limit, $date_type) {
		// First we get orders from the OrderTable
		$order_filter = [];
		if ($dt_from) {
			$order_filter['>=' . $date_type] = $dt_from;
		}
		if ($dt_to) {
			$order_filter['<=' . $date_type] = $dt_to;
		}
		if ($site_id) {
			$order_filter['=LID'] = $site_id;
		}

		$order_params = [
			'filter' => $order_filter,
			'select' => ['ID', 'USER_ID', 'LID', 'DATE_UPDATE', 'DATE_INSERT'],
			'order' => [$date_type => 'DESC'],
		];
		if ($limit) {
			$order_params['limit'] = (int) $limit;
		}

		$orders = [];
		$order_res = OrderTable::getList($order_params);
		while ($order_row = $order_res->fetch()) {
			$orders[] = $order_row;
		}

		// Now for each order we get its elements from BasketTable
		$baskets = [];
		foreach ($orders as $order) {
			$order_id = (int) $order['ID'];
			$user_id = (int) $order['USER_ID'];

			// Get basket items for this order
			$basket_filter = [
				'=ORDER_ID' => $order_id,
				'=DELAY'    => 'N',
				'=CAN_BUY'  => 'Y',
			];

			$basket_params = [
				'filter' => $basket_filter,
				'select' => [
					'ID',
					'FUSER_ID',
					'PRODUCT_ID',
					'NAME',
					'PRICE',
					'QUANTITY',
					'CURRENCY',
					'DATE_UPDATE',
					'DATE_INSERT',
					'DISCOUNT_PRICE',
					'CUSTOM_PRICE',
					'VAT_RATE',
					'VAT_INCLUDED',
				],
			];

			$key = $user_id . '_' . self::getLastOrderId($user_id, $order_id);
			$baskets[$key] = [
				'ID'          => $key,
				'FUSER_ID'    => 0, // For orders FUSER_ID may not be relevant
				'USER_ID'     => $user_id,
				'SITE_ID'     => $order['LID'],
				'DATE_UPDATE' => self::formatDateValue($order['DATE_UPDATE']),
				'DATE_INSERT' => self::formatDateValue($order['DATE_INSERT']),
				'PRICE_SUM'   => 0,
				'ORDER_ID'    => $order_id,
				'ITEMS'       => [],
			];

			$basket_res = BasketTable::getList($basket_params);
			while ($basket_row = $basket_res->fetch()) {
				$row_update_ts = self::formatDateValue($basket_row['DATE_UPDATE']);
				$row_insert_ts = self::formatDateValue($basket_row['DATE_INSERT']);

				// Update basket dates if items have later dates
				if ($row_update_ts && $row_update_ts > $baskets[$key]['DATE_UPDATE']) {
					$baskets[$key]['DATE_UPDATE'] = $row_update_ts;
				}
				if ($row_insert_ts && (!$baskets[$key]['DATE_INSERT'] || $row_insert_ts < $baskets[$key]['DATE_INSERT'])) {
					$baskets[$key]['DATE_INSERT'] = $row_insert_ts;
				}

				$baskets[$key]['PRICE_SUM'] += (float) $basket_row['PRICE'] * (float) $basket_row['QUANTITY'];

				$baskets[$key]['ITEMS'][] = [
					'BASKET_ID'      => (int) $basket_row['ID'],
					'PRODUCT_ID'     => (int) $basket_row['PRODUCT_ID'],
					'NAME'           => $basket_row['NAME'],
					'PRICE'          => $basket_row['PRICE'],
					'QUANTITY'       => $basket_row['QUANTITY'],
					'CURRENCY'       => $basket_row['CURRENCY'],
					'CUSTOM_PRICE'   => $basket_row['CUSTOM_PRICE'],
					'DISCOUNT_PRICE' => $basket_row['DISCOUNT_PRICE'],
					'VAT_RATE'       => $basket_row['VAT_RATE'],
					'VAT_INCLUDED'   => $basket_row['VAT_INCLUDED'],
					'DATE_UPDATE'    => $row_update_ts,
					'DATE_INSERT'    => $row_insert_ts,
					'ORDER_ID'       => $order_id,
				];
			}

			// If there are no items in the order, skip it
			if (empty($baskets[$key]['ITEMS'])) {
				unset($baskets[$key]);
			}
		}

		return $baskets;
	}

	/**
	 * Stage 3: Merging into one list by ascending date
	 */
	private static function mergeAndSortBaskets($baskets_without_orders, $ordered_baskets, $forgotten_hours, $date_type) {
		// Merge all baskets
		$all_baskets = array_merge($baskets_without_orders, $ordered_baskets);

		// Determine STATUS_ID for each basket
		$threshold_time = null;
		if ($forgotten_hours !== null) {
			$threshold_time = (new \DateTime())->modify('-' . (int) $forgotten_hours . ' hours')->getTimestamp();
		}

		foreach ($all_baskets as $key => $basket) {
			// Use the selected date type for status determination
			$current_date_ts = ($date_type === self::DATE_TYPE_UPDATE) ? $basket['DATE_UPDATE'] : $basket['DATE_INSERT'];

			if ($basket['ORDER_ID'] > 0) {
				$all_baskets[$key]['STATUS_ID'] = self::STATUS_ORDERED;
			} elseif ($threshold_time !== null && $current_date_ts >= $threshold_time) {
				$all_baskets[$key]['STATUS_ID'] = self::STATUS_ACTIVE;
			} else {
				$all_baskets[$key]['STATUS_ID'] = self::STATUS_FORGOTTEN;
			}
		}

		// Sorting by descending date (from new to old)
		usort($all_baskets, function($a, $b) use ($date_type) {
			$date_a = ($date_type === self::DATE_TYPE_UPDATE) ? $a['DATE_UPDATE'] : $a['DATE_INSERT'];
			$date_b = ($date_type === self::DATE_TYPE_UPDATE) ? $b['DATE_UPDATE'] : $b['DATE_INSERT'];

			if ($date_a == $date_b) {
				return 0;
			}
			return ($date_a > $date_b) ? -1 : 1;
		});

		return $all_baskets;
	}

	private static function normalizeDate($value) {
		if ($value instanceof Type\DateTime) {
			return $value;
		}
		if ($value instanceof \DateTimeInterface) {
			return Type\DateTime::createFromPhp($value);
		}
		if (is_numeric($value)) {
			return Type\DateTime::createFromTimestamp((int) $value);
		}
		if (is_string($value) && $value !== '') {
			return new Type\DateTime($value);
		}
		return null;
	}

	/**
	 * Get last order ID for user
	 */
	private static function getLastOrderId($user_id, $before_order_id=0) {
		if (!$user_id) {
			return 0;
		}

		$filter = ['=USER_ID' => $user_id];
		if ($before_order_id > 0) {
			$filter['<ID'] = $before_order_id;
		}

		$res = OrderTable::getList([
			'filter' => $filter,
			'select' => ['ID'],
			'order'  => ['ID' => 'DESC'],
			'limit'  => 1
		]);

		if ($row = $res->fetch()) {
			return (int) $row['ID'];
		}

		return 0;
	}

	private static function formatDateValue($value) {
		if ($value instanceof Type\DateTime) {
			return $value->getTimestamp();
		}
		if ($value instanceof \DateTimeInterface) {
			return $value->getTimestamp();
		}
		if (is_numeric($value)) {
			return (int) $value;
		}
		return null;
	}
}
