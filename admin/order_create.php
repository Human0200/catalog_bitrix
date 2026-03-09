<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/interface/admin_lib.php");

use \SProduction\Integration\Rest,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\Page\Asset,
	\Bitrix\Sale;

CModule::IncludeModule("sproduction.integration");

CUtil::InitJSCore(['jquery']);

if ($_REQUEST['PLACEMENT'] == 'CRM_DEAL_LIST_TOOLBAR') {
	$data = json_decode($_REQUEST['PLACEMENT_OPTIONS'], true);
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
		// Redirect to the order edit page
		header('Location: /bitrix/admin/sale_order_create.php?lang=ru&SITE_ID=s1&IFRAME=Y');
		exit();
	}
}
