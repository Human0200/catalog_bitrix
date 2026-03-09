<?
error_reporting( E_ERROR );

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Application,
	SProduction\Integration\Rest,
	SProduction\Integration\Integration,
	\SProduction\Integration\CheckState,
	SProduction\Integration\Settings;
use SProduction\Integration\PortalHandlers;

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
	default: // MODULE_INSTALLED
}

if (! Rest::checkConnection()) {
	return;
}

// Check source of event
$auth_info = Rest::getAuthInfo();

if ($_REQUEST['member_id'] != $auth_info['member_id']) {
	return;
}

if (in_array($_REQUEST['PLACEMENT'], ['CRM_DEAL_DETAIL_TOOLBAR', 'CRM_DEAL_LIST_TOOLBAR', 'CRM_DEAL_DETAIL_TAB'])) {
	// Authorization
	if (!$USER->IsAuthorized()) {
		$cred = [
			'access_token' => $_REQUEST['AUTH_ID'],
			'refresh_token' => $_REQUEST['REFRESH_ID'],
		];
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
	// Redirect to the order edit page
	if ($_REQUEST['PLACEMENT'] == 'CRM_DEAL_DETAIL_TOOLBAR') {
		header('Location: /bitrix/admin/sprod_integr_order_edit.php?' . http_build_query($_REQUEST));
	}
	elseif ($_REQUEST['PLACEMENT'] == 'CRM_DEAL_LIST_TOOLBAR') {
		header('Location: /bitrix/admin/sprod_integr_order_create.php?' . http_build_query($_REQUEST));
	}
	elseif ($_REQUEST['PLACEMENT'] == 'CRM_DEAL_DETAIL_TAB') {
		header('Location: /bitrix/admin/sprod_integr_products_edit.php?' . http_build_query($_REQUEST));
	}
}
