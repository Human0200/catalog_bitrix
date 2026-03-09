<?php
/**
 * Integration
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

\Bitrix\Main\Loader::includeModule("iblock");
\Bitrix\Main\Loader::includeModule("sale");

use Bitrix\Main,
	Bitrix\Main\Type,
	Bitrix\Main\Entity,
	Bitrix\Main\Localization\Loc,
	Bitrix\Main\SiteTable,
	Bitrix\Sale;

Loc::loadMessages(__FILE__);

class Integration
{
	const MODULE_ID = 'sproduction.integration';
	const APP_HANDLER = '/bitrix/sprod_integr_auth.php';
	const FILELOG = '/upload/sprod_integr_log.txt';
	const DEAL_MEASURE_CODE_DEF = 796;
    public static $SERVER_ADDR;
    protected static $MANUAL_RUN = false;

	public static function getServerAddr() {
		if (!self::$SERVER_ADDR) {
			self::$SERVER_ADDR = Settings::get("site");
		}
		return self::$SERVER_ADDR;
	}

	public static function getAppLink() {
		$link = self::getServerAddr() . self::APP_HANDLER;
		return $link;
	}

	public static function setBulkRun() {
		self::$MANUAL_RUN = true;
		Rest::setBulkRun();
	}

	public static function isBulkRun() {
		return self::$MANUAL_RUN;
	}


	/**
	 * Sync by event of order changed
	 *
	 * @param $order_id
	 * @param $status_id
	 */

	public static function eventOnSaleOrderSaved(\Bitrix\Main\Event $event) {
		global $USER;
		\SProdIntegration::setLogLabel();
		if (!\Bitrix\Main\Loader::includeModule(self::MODULE_ID)) {
			return;
		}
		if (! Rest::checkConnection()) {
			return;
		}
		// Get the order
		$order = $event->getParameter("ENTITY");
		$order_data = StoreData::getOrderInfo($order);
		$order_id = $order_data['ID'];
		$user_id = 0;
		if (is_object($USER)) {
			$user_id = $USER->GetID();
		}
		\SProdIntegration::Log('(eventOnSaleOrderSaved) call for order ' . $order_id . ' by user ' . $user_id);
		if ($order_id) {
			\SProdIntegration::Log('(eventOnSaleOrderSaved) run sync');
			// Protection against duplication
			$is_new = $event->getParameter("IS_NEW");
			if ($is_new) {
				\SProdIntegration::Log('(eventOnSaleOrderSaved) new order ' . $order_id);
				OrderAddLock::add($order_id);
			}
			// Sync
			Rest::sendBgrRequest("/bitrix/sprod_integr_bgr_run.php", [
				'order_data' => \Bitrix\Main\Web\Json::encode($order_data),
				'new_values' => \Bitrix\Main\Web\Json::encode(self::formatOrderNewValues($event->getParameter("VALUES"))),
				'new' => $is_new,
			]);
		}
	}

	/**
	 * Sync by event of deal changed
	 *
	 * @param $fields
	 */

	public static function eventOnAfterCrmDealUpdate($fields) {
		$deal_id = $fields['ID'];
	}


	/**
	 * Format list of order changed field
	 */

	public static function formatOrderNewValues($fields) {
		$new_values = false;
		if (is_array($fields)) {
			$new_values = [];
			foreach ($fields as $k => $value) {
				if (in_array(gettype($value), ['boolean', 'integer', 'double', 'string'])) {
					$new_values[$k] = $value;
				}
			}
		}
		return $new_values;
	}


    /**
     * Sync all orders by period
     *
     * @param $sync_period
     */

	public static function syncStoreToCRM($sync_period=0) {
        global $DB;
	    if (Rest::checkConnection()) {
		    Rest::setBulkRun();
		    \SProdIntegration::Log('(syncStoreToCRM) run period ' . $sync_period);
		    // List of orders, changed by last period (if period is not set than get all orders)
	        $filter = [];
	        if ($sync_period > 0) {
	            $filter['>DATE_UPDATE'] = date($DB->DateFormatToPHP(\CSite::GetDateFormat("FULL")), time() - $sync_period);
	        }
	        $select = ['ID'];
	        $db = \CSaleOrder::GetList(["DATE_UPDATE" => "DESC"], $filter, false, false, $select);
		    while ($order_item = $db->Fetch()) {
			    $order      = Sale\Order::load($order_item['ID']);
			    $order_data = StoreData::getOrderInfo($order);
			    OrderAddLock::add($order_data['ID']);
			    try {
				    SyncDeal::runSync($order_data);
			    }
			    catch (\Exception $e) {
				    \SProdIntegration::Log('(syncStoreToCRM) can\'t sync of order ' . $order_data['ID']);
			    }
	        }
		    \SProdIntegration::Log('(syncStoreToCRM) success');
	    }
    }

}
