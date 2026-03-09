<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php" );
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/interface/admin_lib.php");

$MODULE_ID = "sproduction.integration";

CModule::IncludeModule($MODULE_ID);

use \SProduction\Integration\Integration,
	\SProduction\Integration\Utilities,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\Page\Asset,
	\Bitrix\Sale;

Loc::LoadMessages(__FILE__);
$loc_messages = Loc::loadLanguageFile(__FILE__);

$scripts = ['/bitrix/js/'.$MODULE_ID.'/page_fbasket_profile_edit.js'];
$styles = [];

$profile_id = intval($_GET['id']);
$profile = \SProduction\Integration\FbasketProfilesTable::getById($profile_id);
$profile_name = '';
if ($profile && isset($profile['name'])) {
	$profile_name = Utilities::convEncToWin($profile['name']);
}

require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/" . $MODULE_ID . "/admin/include/header.php" );
?>
    <script>
        profile_id = '<?=$profile_id;?>';
        messages.ru.page = {
		<?foreach ($loc_messages as $k => $message):?>
		<?=$k;?>: '<?=str_replace(array("\n", "\r"), '', $message);?>',
		<?endforeach;?>
        };
    </script>
    <div id="app">
        <div class="sprod-integr-page" id="sprod_integr_fbasket_profile_edit_page">
            <div class="wrapper iframe-wrapper">
                <div class="container-fluid pl-3 pr-3">

                    <div class="page-title-box">
                        <loader :counter="loader_counter" class="mb-3"></loader>
                        <h4 class="page-title"><?=Loc::getMessage('SP_CI_PAGE_FBASKET_PROFILE_EDIT_TITLE', ['#TITLE#' => $profile_name]);?></h4>
                    </div>

                    <main-errors :errors="errors" :warnings="warnings"></main-errors>

                    <div :class="{ 'block-disabled': loader_counter }">
                        <b-tabs
                            active-nav-item-class="bg-info"
                            pills>

                            <b-tab title="<?=Loc::getMessage('SP_CI_FBASKET_PROFILE_EDIT_TAB_MAIN');?>" active>
                                <b-card-text>
                                    <fbasket-profile-main
                                        :sites_list="info.site.sites"
                                        :categ_list="info.crm.directions"
                                        :users_list="info.crm.users"
                                        :sources_list="info.crm.sources"
                                        :stages_list="info.crm.stages"
                                        @load_start="startLoadingInfo"
                                        @load_stop="stopLoadingInfo"
                                        @block_update="updateBlocks"
                                    ></fbasket-profile-main>
                                </b-card-text>
                            </b-tab>

                            <b-tab title="<?=Loc::getMessage('SP_CI_FBASKET_PROFILE_EDIT_TAB_CONTACTS');?>">
                                <b-card-text>
                                    <fbasket-profile-contacts
                                        :site_field_list="info.site.contact_fields"
                                        :crm_contact_field_list="info.crm.contact_fields"
                                        :crm_contact_search_field_list="info.crm.contact_search_fields"
                                        :ugroup_list="info.crm.user_groups"
                                        @load_start="startLoadingInfo"
                                        @load_stop="stopLoadingInfo"
                                        @block_update="updateBlocks"
                                    ></fbasket-profile-contacts>
                                </b-card-text>
                            </b-tab>

                            <b-tab title="<?=Loc::getMessage('SP_CI_FBASKET_PROFILE_EDIT_TAB_STATUSES');?>">
                                <b-card-text>
                                    <fbasket-profile-statuses
                                        :stage_list="info.crm.stages"
                                        @load_start="startLoadingInfo"
                                        @load_stop="stopLoadingInfo"
                                        @block_update="updateBlocks"
                                    ></fbasket-profile-statuses>
                                </b-card-text>
                            </b-tab>

                            <b-tab title="<?=Loc::getMessage('SP_CI_FBASKET_PROFILE_EDIT_TAB_FIELDS');?>">
                                <b-card-text>
                                    <fbasket-profile-fields
                                        :crm_deal_field_list="info.crm.deal_fields"
                                        :site_basket_field_list="info.site.basket_fields"
                                        @load_start="startLoadingInfo"
                                        @load_stop="stopLoadingInfo"
                                        @block_update="updateBlocks"
                                    ></fbasket-profile-fields>
                                </b-card-text>
                            </b-tab>

                        </b-tabs>
                    </div>

                </div> <!-- end container -->
            </div>

        </div>
    </div>

<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/" . $MODULE_ID . "/admin/include/footer.php" );
