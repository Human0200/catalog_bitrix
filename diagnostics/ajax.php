<?
/*
 * Diagnostics page
 */

error_reporting( E_ERROR );

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

\Bitrix\Main\Loader::includeModule('sproduction.integration');

use \SProduction\Integration\Integration,
	\SProduction\Integration\RemoteDiagAccess,
	\SProduction\Integration\RemoteDiag,
	\SProduction\Integration\RemoteDiagLogs,
	\SProduction\Integration\FileLogControl,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\Page\Asset,
	\Bitrix\Sale;


/**
 * Check access
 */

$secure_code = $_REQUEST['sc'];
if (!RemoteDiagAccess::checkAccess($secure_code)) {
	die('Access Denied');
}


/**
 * Prepare
 */

Loc::loadMessages(__FILE__);

$params = json_decode(file_get_contents('php://input'), true);

$action = trim($_REQUEST['action'] ?? '');
$result = [];
$result['status'] = 'error';
$result['log'] = [];
$lock_result = false;


/**
 * Actions
 */

try {
	switch ($action) {

		case 'get_all_info':
			$result['info'] = RemoteDiag::getMainInfo();
			$result['options'] = RemoteDiag::getOptions();
			$result['profiles'] = RemoteDiag::getProfiles();
			$result['fbasket_profiles'] = RemoteDiag::getFbasketProfiles();
			$result['store_fields'] = RemoteDiag::getStoreFields();
			$result['crm_fields'] = RemoteDiag::getCRMFields();
			$result['handlers'] = RemoteDiag::getHandlers();
			$result['logs'] = [];
			$result['filelog'] = RemoteDiag::getFilelog();
			$result['log_queries'] = RemoteDiag::getLogQueries();
			$result['status'] = 'ok';
			break;

		// File log
		case 'filelog_save':
			$fields = $params['fields'];
			FileLogControl::changeStatus($fields['active']);
			$result['status'] = 'ok';
			break;

		case 'filelog_reset':
			FileLogControl::reset();
			$result['status'] = 'ok';
			break;

		// Log queries
		case 'log_queries_save':
			$fields = $params['fields'];
			$is_active = false;
			if (is_array($fields)) {
				if (array_key_exists('active', $fields)) {
					$is_active = (bool)$fields['active'];
				} elseif (array_key_exists('log_queries', $fields)) {
					$is_active = (bool)$fields['log_queries'];
				}
			}
			\SProduction\Integration\Settings::save('log_queries', $is_active ? 'Y' : 'N');
			$result['status'] = 'ok';
			break;

		// Logs search
		case 'logs_find_labels_by_order':
			$orderId = intval($params['id']);
			// Получаем данные заказа и связанной сделки
			$orderAndDeal = RemoteDiag::getOrderDataWithRelatedDeal($orderId);
			$dealId = $orderAndDeal['deal'] ? intval($orderAndDeal['deal']['ID']) : null;
			$result['labels'] = RemoteDiagLogs::findLabelsByOrderAndDeal($orderId, $dealId);
			$result['order_data'] = $orderAndDeal['order'];
			$result['deal_data'] = $orderAndDeal['deal'];
			$result['status'] = 'ok';
			break;

		case 'logs_find_labels_by_deal':
			$dealId = intval($params['id']);
			// Получаем данные сделки и связанного заказа
			$dealAndOrder = RemoteDiag::getDealDataWithRelatedOrder($dealId);
			$orderId = $dealAndOrder['order'] ? intval($dealAndOrder['order']['ID']) : null;
			$result['labels'] = RemoteDiagLogs::findLabelsByOrderAndDeal($orderId, $dealId);
			$result['deal_data'] = $dealAndOrder['deal'];
			$result['order_data'] = $dealAndOrder['order'];
			$result['status'] = 'ok';
			break;

		case 'logs_get_content_by_label':
			$label = trim($params['label']);
			$result['content'] = RemoteDiagLogs::getLogLinesByLabel($label);
			$result['status'] = 'ok';
			break;

		case 'logs_get_content_by_labels':
			$labels = $params['labels'];
			if (!is_array($labels)) {
				$labels = [$labels];
			}
			// Очищаем и валидируем метки
			$labels = array_map('trim', $labels);
			$labels = array_filter($labels);
			$result['content'] = RemoteDiagLogs::getLogLinesByLabels($labels);
			$result['status'] = 'ok';
			break;

		case 'test':
			$result['data'] = 123;
			$result['status'] = 'ok';
			break;
	}
} catch (Exception $e) {
	$result['status'] = 'error';
	$result['message'] = $e->getMessage().' ['.$e->getCode().']';
}


/**
 * Result
 */

echo \Bitrix\Main\Web\Json::encode($result);
