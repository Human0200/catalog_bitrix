<?
error_reporting( E_ERROR );

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Application,
	Bitrix\Main\Localization\Loc,
	SProduction\Integration\Rest,
	SProduction\Integration\Integration,
	\SProduction\Integration\CheckState,
	SProduction\Integration\Settings,
	SProduction\Integration\PortalHandlers,
	SProduction\Integration\PortalCustomFields,
	SProduction\Integration\PortalPlacements;

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

Loc::loadMessages(__FILE__);

if (!$USER->IsAdmin()) {
	echo Loc::getMessage("SP_CI_AUTH_ADMIN_REQUIRED");
	die();
}

Rest::restToken($_REQUEST['code']);

// Add placements and event handlers
if (Rest::checkConnection()) {
	if (CheckState::isSyncActive()) {
		PortalHandlers::reg();
		PortalPlacements::setAll();
		PortalCustomFields::setAll();
	}
    LocalRedirect('/bitrix/admin/sprod_integr_settings.php?lang=' . LANGUAGE_ID);
}
else {
    echo 'Authorization error';
}
