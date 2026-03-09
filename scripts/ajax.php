<?
/*
 * Diagnostics page
 */

error_reporting( E_ERROR );

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

\Bitrix\Main\Loader::includeModule('sale');
\Bitrix\Main\Loader::includeModule('sproduction.integration');

use
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\Page\Asset,
	\Bitrix\Sale,
	\SProduction\Integration\Integration,
	\SProduction\Integration\RemoteDiagAccess,
	\SProduction\Integration\RemoteDiag,
	\SProduction\Integration\FileLogControl,
	\SProduction\Integration\Secure;


/**
 * Check access
 */

$secure_code = $_REQUEST['sc'];
if (!Secure::checkAjaxSCode($secure_code)) {
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

		/**
		 * Custom fields
		 */

		// Save new user for order
		case 'custom_fields_orderuser_save':
			$order_id = (int) $params['order_id'];
			$user_id = (int) $params['user_id'];
			if ($order_id && $user_id) {
				$order = Sale\Order::load($order_id);
				$order->setFieldNoDemand('USER_ID', $user_id);
				$order->save();
			}
			$result['status'] = 'ok';
			break;

		/**
		 * Other functions
		 */

		// Users search (by text or by ID)
		case 'otherfunc_find_user':
			$s_string = $params['search'];
			$user_list = \SProduction\Integration\StoreUser::findUsers($s_string, 15);
			foreach ($user_list as $item) {
				$list[] = [
					'code' => $item['id'],
					'label' => $item['name'] ? ($item['name'] . ' (' . $item['email'] . ')') : $item['email']
				];
			}
			$result['list'] = $list;
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
