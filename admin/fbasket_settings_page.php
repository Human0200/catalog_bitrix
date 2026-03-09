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

$scripts = ['/bitrix/js/'.$MODULE_ID.'/page_fbasket.js'];
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
        <div class="sprod-integr-page" id="sprod_integr_fbasket_settings_page">
            <div class="wrapper iframe-wrapper">
                <div class="container-fluid pl-3 pr-3">

                    <div class="page-title-box">
                        <loader :counter="loader_counter"></loader>
                        <h4 class="page-title"><?=Loc::getMessage('SP_CI_FBASKET_SETTINGS_TITLE');?></h4>
                    </div>

                    <main-errors :errors="errors" :warnings="warnings"></main-errors>

                    <div class="alert alert-info">
                        <i class="fa fa-info-circle mr-1"></i>
                        <?=Loc::getMessage('SP_CI_FBASKET_SETTINGS_INFO');?>
                    </div>

                    <b-row>
                        <b-col>
                            <settings-forgotten-basket @block_update="updateBlocks"></settings-forgotten-basket>
                        </b-col>
                        <b-col>
                            <info-block title-key="SP_CI_SETTINGS_FORGOTTEN_BASKET_INFO_TITLE" text-key="SP_CI_SETTINGS_FORGOTTEN_BASKET_INFO_TEXT"></info-block>
                        </b-col>
                    </b-row>

                    <b-row v-if="sync_start_date" class="mt-3">
                        <b-col>
                            <div class="alert alert-info mb-0">
                                <i class="fa fa-info-circle mr-1"></i>
                                {{ $t("page.SP_CI_FBASKET_SYNC_START_DATE_NOTICE") }} <strong>{{ sync_start_date }}</strong>
                            </div>
                        </b-col>
                    </b-row>
                    <b-row class="mt-3">
                        <b-col>
                            <settings-background-sync @block_update="updateBlocks"></settings-background-sync>
                        </b-col>
                        <b-col>
                            <info-block title-key="SP_CI_SETTINGS_BACKGROUND_SYNC_INFO_TITLE" text-key="SP_CI_SETTINGS_BACKGROUND_SYNC_INFO_TEXT"></info-block>
                        </b-col>
                    </b-row>

                    <b-row class="mt-3">
                        <b-col>
                            <settings-man_fbasket_sync></settings-man_fbasket_sync>
                        </b-col>
                        <b-col>
                            <info-block title-key="SP_CI_SETTINGS_MAN_FBASKET_SYNC_INFO_TITLE" text-key="SP_CI_SETTINGS_MAN_FBASKET_SYNC_INFO_TEXT"></info-block>
                        </b-col>
                    </b-row>

                </div> <!-- end container -->
            </div>

        </div>
    </div>

<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/" . $MODULE_ID . "/admin/include/footer.php" );
?>