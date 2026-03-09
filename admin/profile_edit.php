<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php" );
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/interface/admin_lib.php");

use \SProduction\Integration\Integration,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\Page\Asset,
	\Bitrix\Sale;

CModule::IncludeModule("sproduction.integration");

$MODULE_ID = SProdIntegration::MODULE_ID;

Loc::LoadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/' . $MODULE_ID . '/main.php');

$APPLICATION->SetTitle(Loc::getMessage('SP_CI_MENU_NAME'));

$profile_id = intval($_GET['id']);

CUtil::InitJSCore(array("jquery"));
Asset::getInstance()->addString('<script>
function iframeResize() {
    var iFrameID = document.getElementById(\'sprod_integr_profile_edit_frame\');
    if (iFrameID) {
        var cont_h = (iFrameID.contentWindow.document.body.scrollHeight + 30);
        $(\'#sprod_integr_profile_edit_frame\').height(cont_h + \'px\');
    }
}
</script>');
Asset::getInstance()->addString('<style>
.sprod-integr-frame { height: 600px; }
</style>');

require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php" );
?>
    <iframe src="sprod_integr_profile_edit_page.php?id=<?=$profile_id;?>" class="sprod-integr-frame" id="sprod_integr_profile_edit_frame"></iframe>
<?
require( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php" );
