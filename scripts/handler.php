<?
/*
 * Prepare check
 */

error_reporting( E_ERROR );

use SProduction\Integration\ExtEventsQueue;

$ext_event_type = $_REQUEST['event'];
if (!in_array($ext_event_type, ['ONCRMDEALADD', 'ONCRMDEALUPDATE'])) {
	return;
}

include $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sproduction.integration/lib/exteventsqueue.php';
// DB connection data
$settings = require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/.settings.php');
if (isset($settings['connections']['value']['default'])) {
	$db_conn = $settings['connections']['value']['default'];
}
else {
	include $_SERVER['DOCUMENT_ROOT'] . '/bitrix/php_interface/dbconn.php';
	$db_conn = [
		'host' => $DBHost,
		'database' => $DBName,
		'login' => $DBLogin,
		'password' => $DBPassword,
	];
}

$deal_ids = [];

$queue = new ExtEventsQueue($db_conn['host'], $db_conn['database'], $db_conn['login'], $db_conn['password']);

$deal_id = (int)$_REQUEST['data']['FIELDS']['ID'];
$queue->saveLog('(crm handler prepare) input deal: ' . $deal_id);
// Set a mark that deal changed that the changes in the order are expecting
$queue->setDealLastChangedMark($deal_id);

// Split the processing of events that came simultaneously
usleep(rand(100000, 1000000));

if ($deal_id) {
	$deal_id_str = $deal_id . ($ext_event_type=='ONCRMDEALADD'?'a':'u');
	$queue->add($deal_id_str);
	sleep(3);
	$deal_ids = $queue->getAndClear(50);
}

if (empty($deal_ids)) {
	return;
}


/*
 * Main part
 */

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$incl_res = Bitrix\Main\Loader::includeSharewareModule('sproduction.integration');
switch ($incl_res) {
	case Bitrix\Main\Loader::MODULE_NOT_FOUND:
		echo 'Module sproduction.integration not found.';
		die();
		break;
	case Bitrix\Main\Loader::MODULE_DEMO_EXPIRED:
		echo 'Module sproduction.integration demo expired.';
		die();
		break;
	case Bitrix\Main\Loader::MODULE_DEMO:
		echo 'Module sproduction.integration demo mode.';
		break;
	default: // MODULE_INSTALLED
}

use Bitrix\Main\Config\Option,
    Bitrix\Sale,
    SProduction\Integration\Rest,
    SProduction\Integration\Integration,
	SProduction\Integration\OfflineEvents;

if (! Rest::checkConnection()) {
    return;
}

// Check source of event
$auth_info = Rest::getAuthInfo();
if ($_REQUEST['auth']['member_id'] != $auth_info['member_id']) {
	return;
}

// Process event by synchronous mode
//Rest::disableStoreEventsBgrRun();

// Check all last events and process
OfflineEvents::processEvents($deal_ids);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
