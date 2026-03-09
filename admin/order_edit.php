<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/interface/admin_lib.php");

use \SProduction\Integration\Rest,
	\SProduction\Integration\Integration,
	\SProduction\Integration\BoxMode,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\Page\Asset,
	\Bitrix\Sale;
use SProduction\Integration\Settings;

CModule::IncludeModule("sproduction.integration");

if ($_REQUEST['PLACEMENT'] == 'CRM_DEAL_DETAIL_TOOLBAR') {
	$data = json_decode($_REQUEST['PLACEMENT_OPTIONS'], true);
	$deal_id = intVal($data['ID']);
	if ($deal_id) {
		$cred = [
            'access_token' => $_REQUEST['AUTH_ID'],
            'refresh_token' => $_REQUEST['REFRESH_ID'],
        ];
		// Authorization
		if (!$USER->IsAuthorized()) {
			$res = Rest::execute('user.current', [], $cred);
			$user_email = $res['EMAIL'];
			if ($user_email) {
				$user_id = false;
				$db_user = CUser::GetList($by = "ID", $or = "ASC", ["EMAIL" => $user_email], ["FIELDS" => ["ID"]]);
				if ($user = $db_user->Fetch()) {
					$user_id = $user['ID'];
					if ($user_id) {
						$USER->Authorize($user_id);
					}
				}
			}
		}
		if ($USER->IsAuthorized()) {
			// Get order ID
			if (Settings::getSyncMode() == Settings::SYNC_MODE_BOX) {
				$order_id = BoxMode::findOrderByDeal($deal_id);
			}
			else {
				$deal = Rest::execute('crm.deal.get', [
					'id' => $deal_id,
				], $cred);
				if (isset($deal['ID'])) {
					$order_id = (int) $deal[Settings::getOrderIDField()];
				}
			}
			// Redirect to the order edit page
			if ($order_id) {
				header('Location: /bitrix/admin/sale_order_edit.php?ID=' . $order_id . '&IFRAME=Y');
				exit();
			}
		}
	}
}
