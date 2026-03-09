<?
/*
 * Diagnostics page
 */

error_reporting( E_ERROR );

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use \SProduction\Integration\Integration,
	\SProduction\Integration\RemoteDiagAccess,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\Page\Asset,
	\Bitrix\Sale;

$MODULE_ID = "sproduction.integration";

CModule::IncludeModule($MODULE_ID);


/**
 * Check access
 */

$secure_code = $_REQUEST['sc'];
if (!RemoteDiagAccess::checkAccess($secure_code)) {
	die('Access Denied');
}

/**
 * Display page
 */

Loc::LoadMessages(__FILE__);
$loc_messages = Loc::loadLanguageFile(__FILE__);

$scripts = [
	'/bitrix/js/'.$MODULE_ID.'/components.js',
	'/bitrix/js/'.$MODULE_ID.'/page_remotediag.js?v=' . time()
];
$styles = [];

require_once( $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $MODULE_ID . "/admin/include/header.php" );
?>
	<script>
        secure_code = '<?=CUtil::JSEscape($_REQUEST['sc']);?>';
        messages.ru.page = {
			<?foreach ($loc_messages as $k => $message):?>
			<?=$k;?>: '<?=str_replace(array("\n", "\r"), '', $message);?>',
			<?endforeach;?>
        };
	</script>
	<div id="app">
		<div class="sprod-integr-page" id="sprod_integr_remotediag_page">
			<div class="wrapper iframe-wrapper">
				<div class="container-fluid pl-3 pr-3">

					<div class="page-title-box">
						<loader :counter="loader_counter" class="mb-3"></loader>
						<h4 class="page-title"><?=Loc::getMessage('SP_CI_DIAGNOSTICS_PAGE_TITLE');?></h4>
					</div>

                    <main-errors :errors="errors" :warnings="warnings"></main-errors>

					<div>
						<b-tabs
							active-nav-item-class="bg-info">

							<b-tab title="<?=Loc::getMessage('SP_CI_DIAGNOSTICS_PAGE_INFO');?>">
                                <info-table
                                    :blocks="info"
                                />
							</b-tab>

							<b-tab title="<?=Loc::getMessage('SP_CI_DIAGNOSTICS_PAGE_OPTIONS');?>">
                                <info-table
                                    :blocks="options"
                                />
							</b-tab>

							<b-tab title="<?=Loc::getMessage('SP_CI_DIAGNOSTICS_PAGE_PROFILES');?>">
                                <info-table
                                    :blocks="profiles"
                                />
							</b-tab>

							<b-tab title="<?=Loc::getMessage('SP_CI_DIAGNOSTICS_PAGE_FBASKET_PROFILES');?>">
                                <info-table
                                    :blocks="fbasket_profiles"
                                />
							</b-tab>

							<b-tab title="<?=Loc::getMessage('SP_CI_DIAGNOSTICS_PAGE_STORE_FIELDS');?>">
                                <info-table
                                    :blocks="store_fields"
                                />
							</b-tab>

							<b-tab title="<?=Loc::getMessage('SP_CI_DIAGNOSTICS_PAGE_CRM_FIELDS');?>">
                                <info-table
                                    :blocks="crm_fields"
                                />
							</b-tab>

                            <b-tab title="<?=Loc::getMessage('SP_CI_DIAGNOSTICS_PAGE_HANDLERS');?>">
                                <info-table
                                    :blocks="handlers"
                                />
                            </b-tab>

                            <b-tab title="<?=Loc::getMessage('SP_CI_DIAGNOSTICS_PAGE_LOGS');?>">
                                <logs-search @block_update="updateBlocks" :fields="logs" :filelog="filelog" :log_queries="log_queries"></logs-search>
                            </b-tab>

						</b-tabs>
					</div>

				</div> <!-- end container -->
			</div>

		</div>
	</div>

<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/" . $MODULE_ID . "/admin/include/footer.php" );
