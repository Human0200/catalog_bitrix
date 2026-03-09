<?
error_reporting( E_ERROR );

use SProduction\Integration\BgrRunLock;
include $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sproduction.integration/lib/bgrrunlock.php';
//for ($i=0; $i<10 && BgrRunLock::isBgrRunLock(); $i++) {
//	sleep(1);
//}
BgrRunLock::setBgrRunLock();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use SProduction\Integration\Integration,
	SProduction\Integration\Settings,
	SProduction\Integration\Rest,
	SProduction\Integration\ProfilesTable,
	SProduction\Integration\ProfileInfo,
	SProduction\Integration\StoreEventsQueue,
	SProduction\Integration\OrderAddLock,
	SProduction\Integration\OrderProcessLock,
	SProduction\Integration\OfflineEvents,
	SProduction\Integration\SyncDealControl,
	Bitrix\Main\Config\Option,
	Bitrix\Main\Localization\Loc,
	Bitrix\Main\Loader,
	Bitrix\Sale,
	SProduction\Integration\StoreData,
	SProduction\Integration\SyncDeal;

Loader::includeModule('sale');
$incl_res = Loader::includeSharewareModule('sproduction.integration');
switch ($incl_res) {
	case Loader::MODULE_NOT_FOUND:
		echo 'Module sproduction.integration not found.';
		BgrRunLock::clearBgrRunLock();
		die();
	case Loader::MODULE_DEMO_EXPIRED:
		echo 'Module sproduction.integration demo expired.';
		die();
	default: // MODULE_INSTALLED
}

$log_label = $_REQUEST['log_label'];
\SProdIntegration::setLogLabel($log_label . '_bgr' . mt_rand(1000, 9999));

$secret     = $_REQUEST['secret_key'];
$order_data = \Bitrix\Main\Web\Json::decode($_REQUEST['order_data'] ?? '{}');
$new_values = \Bitrix\Main\Web\Json::decode($_REQUEST['new_values'] ?? '{}');
$order_id = $order_data['ID'];
$is_new     = $_REQUEST['new'];
$order_ids = [];
$queue = new StoreEventsQueue();

\SProdIntegration::Log('(bgr_run) run for order ' . $order_id);

if ($secret != Rest::getBgrRequestSecret()) {
	\SProdIntegration::Log('(bgr_run) access error');
	BgrRunLock::clearBgrRunLock();
	return;
}
if (!$order_id) {
	\SProdIntegration::Log('(bgr_run) empty order id');
	BgrRunLock::clearBgrRunLock();
	return;
}

// New order
if ($is_new) {
	$queue->add($order_id);
	OrderAddLock::add($order_id);
	$pause = Settings::get('new_order_pause') ? : 5;
	\SProdIntegration::Log('(bgr_run) adding pause ' . $pause);
	sleep($pause);
	$order_ids = $queue->getAndClear(50);
	\SProdIntegration::Log('(bgr_run) orders for adding ' . print_r($order_ids, 1));
	if (empty($order_ids)) {
		BgrRunLock::clearBgrRunLock();
		return;
	}
	foreach ($order_ids as $order_id) {
		// Waiting, if the order locked
		OrderProcessLock::wait($order_id);
		\SProdIntegration::Log('(bgr_run) order ' . $order_id . ' send');
		$order      = Sale\Order::load($order_id);
		$order_data = StoreData::getOrderInfo($order);
		SyncDeal::runSync($order_data);
	}
}
// Existed order
elseif ($order_data['ID']) {
	// Check that this order not in queue of new orders
	if (!$queue->find($order_data['ID'])) {
		// Waiting for new updates
		$pause = Settings::get('update_order_pause') ? : 3;
		\SProdIntegration::Log('(bgr_run) update pause ' . $pause);
		sleep($pause);
		if (Settings::getSyncMode() != Settings::SYNC_MODE_BOX) {
			// Check updates on deals
			OfflineEvents::processEvents();
		}
		// Waiting, if the order locked
		OrderProcessLock::wait($order_id);
		// Update deal by order
		$order = Sale\Order::load($order_id);
		$order_data = StoreData::getOrderInfo($order, $new_values);
		$create_order_by_update = Settings::get('create_order_by_update') != 'N';
		SyncDeal::runSync($order_data, $create_order_by_update);
	}
}

BgrRunLock::clearBgrRunLock();
