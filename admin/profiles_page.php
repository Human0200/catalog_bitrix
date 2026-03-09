<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php" );
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/interface/admin_lib.php");

$MODULE_ID = "sproduction.integration";

CModule::IncludeModule($MODULE_ID);

use \SProduction\Integration\Integration,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\Page\Asset,
	\Bitrix\Sale;

Loc::LoadMessages(__FILE__);
$loc_messages = Loc::loadLanguageFile(__FILE__);

$scripts = ['/bitrix/js/'.$MODULE_ID.'/page_profiles.js'];
$styles = [];
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/" . $MODULE_ID . "/admin/include/header.php" );
?>
    <script>
        messages.ru.page = {
		<?foreach ($loc_messages as $k => $message):?>
		<?=$k;?>: '<?=str_replace(array("\n", "\r"), '', $message);?>',
		<?endforeach;?>
        };
    </script>
    <div id="app">
        <div class="sprod-integr-page" id="sprod_integr_profiles_page">
            <div class="wrapper iframe-wrapper">
                <div class="container-fluid pl-3 pr-3">

                    <div class="page-title-box">
                        <loader :counter="loader_counter"></loader>
                        <h4 class="page-title"><?=Loc::getMessage('SP_CI_PAGE_PROFILES_TITLE');?></h4>
                    </div>

                    <main-errors :errors="errors" :warnings="warnings"></main-errors>

                    <profiles-list
                        @load_start="startLoadingInfo"
                        @load_stop="stopLoadingInfo"
                        v-if="errors.length==0"
                    ></profiles-list>

                </div> <!-- end container -->
            </div>

        </div>
    </div>

<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/" . $MODULE_ID . "/admin/include/footer.php" );
