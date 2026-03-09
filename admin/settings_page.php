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

$scripts = ['/bitrix/js/'.$MODULE_ID.'/page_settings.js'];
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
        <div class="sprod-integr-page" id="sprod_integr_settings_page">
            <div class="wrapper iframe-wrapper">
                <div class="container-fluid pl-3 pr-3">

                    <div class="page-title-box">
                        <h4 class="page-title"><?=Loc::getMessage('SP_CI_PAGE_SETTINGS_TITLE');?></h4>
                    </div>

                    <main-errors :errors="errors" :warnings="warnings"></main-errors>

                    <b-row>
                        <b-col>
                            <b-alert show variant="success"><strong><?=Loc::getMessage('SP_CI_PAGE_SETTINGS_DATASYNC_AD');?></strong></b-alert>
                        </b-col>
                        <b-col>
                            <b-alert show><i class="fa fa-question-circle"></i> <?=Loc::getMessage('SP_CI_PAGE_SETTINGS_HELP_LINK');?></b-alert>
                        </b-col>
                    </b-row>

                    <b-row>
                        <b-col>
                            <settings-connect @block_update="updateBlocks"></settings-connect>
                            <settings-sync @block_update="updateBlocks"></settings-sync>
                        </b-col>
                        <b-col>
                            <info-block title-key="SP_CI_SETTINGS_CONNECT_INFO_TITLE" text-key="SP_CI_SETTINGS_CONNECT_INFO_TEXT"></info-block>
                        </b-col>
                    </b-row>

                    <settings-active @block_update="updateBlocks"></settings-active>

                    <profiles-warn></profiles-warn>

                    <b-row>
                        <b-col>
                            <settings-add_sync @block_update="updateBlocks"></settings-add_sync>
                        </b-col>
                        <b-col>
                            <info-block title-key="SP_CI_SETTINGS_ADD_SYNC_INFO_TITLE" text-key="SP_CI_SETTINGS_ADD_SYNC_INFO_TEXT"></info-block>
                        </b-col>
                    </b-row>

                    <b-row>
                        <b-col>
                            <settings-man_sync @block_update="updateBlocks"></settings-man_sync>
                        </b-col>
                        <b-col>
                            <info-block title-key="SP_CI_SETTINGS_MAN_SYNC_INFO_TITLE" text-key="SP_CI_SETTINGS_MAN_SYNC_TEXT"></info-block>
                        </b-col>
                    </b-row>


                    <b-alert show><i class="fa fa-question-circle"></i> <?=Loc::getMessage('SP_CI_PAGE_SETTINGS_HELP_LINK_2');?></b-alert>

                    <b-alert show><i class="fa fa-question-circle"></i> <?=Loc::getMessage('SP_CI_PAGE_SETTINGS_HELP_LINK_3');?></b-alert>

                </div> <!-- end container -->
            </div>

        </div>
    </div>

<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/" . $MODULE_ID . "/admin/include/footer.php" );
