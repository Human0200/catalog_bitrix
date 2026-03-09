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

$scripts = ['/bitrix/js/'.$MODULE_ID.'/page_profile_edit.js'];
$styles = [];

$profile_id = intval($_GET['id']);
$profile = \SProduction\Integration\ProfilesTable::getById($profile_id);
$profile_name = Utilities::convEncToWin($profile['name']);

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
        <div class="sprod-integr-page" id="sprod_integr_profile_edit_page">
            <div class="wrapper iframe-wrapper">
                <div class="container-fluid pl-3 pr-3">

                    <div class="page-title-box">
                        <loader :counter="loader_counter" class="mb-3"></loader>
                        <h4 class="page-title"><?=Loc::getMessage('SP_CI_PAGE_PROFILE_EDIT_TITLE', ['#TITLE#' => $profile_name]);?></h4>
                    </div>

                    <main-errors :errors="errors" :warnings="warnings"></main-errors>

                    <div :class="{ 'block-disabled': loader_counter }">
                        <b-tabs
                            active-nav-item-class="bg-info"
                            pills>

                            <b-tab title="<?=Loc::getMessage('SP_CI_PROFILE_EDIT_TAB_MAIN');?>" active>
                                <b-card-text>
                                    <profile-main
                                        :categ_list="info.crm.directions"
                                        :users_list="info.crm.users"
                                        :sources_list="info.crm.sources"
                                        @load_start="startLoadingInfo"
                                        @load_stop="stopLoadingInfo"
                                        @block_update="updateBlocks"
                                    ></profile-main>
                                </b-card-text>
                            </b-tab>

                            <b-tab title="<?=Loc::getMessage('SP_CI_PROFILE_EDIT_TAB_FILTER');?>">
                                <b-card-text>
                                    <profile-filter
                                        :condition_list="info.site.conditions"
                                        @load_start="startLoadingInfo"
                                        @load_stop="stopLoadingInfo"
                                        @block_update="updateBlocks"
                                    ></profile-filter>
                                </b-card-text>
                            </b-tab>

                            <b-tab title="<?=Loc::getMessage('SP_CI_PROFILE_EDIT_TAB_CONTACT');?>">
                                <b-card-text>
                                    <profile-contact
                                        :person_type_list="info.site.person_types"
                                        :site_field_list="info.site.contact_fields"
                                        :crm_contact_field_list="info.crm.contact_fields"
                                        :crm_contact_search_field_list="info.crm.contact_search_fields"
                                        :crm_company_field_list="info.crm.company_fields"
                                        :ugroup_list="info.crm.user_groups"
                                        @load_start="startLoadingInfo"
                                        @load_stop="stopLoadingInfo"
                                        @block_update="updateBlocks"
                                    ></profile-contact>
                                </b-card-text>
                            </b-tab>

                            <b-tab title="<?=Loc::getMessage('SP_CI_PROFILE_EDIT_TAB_STATUSES');?>">
                                <b-card-text>
                                    <profile-statuses
                                        :stage_list="info.crm.stages"
                                        :status_list="info.site.statuses"
                                        @load_start="startLoadingInfo"
                                        @load_stop="stopLoadingInfo"
                                        @block_update="updateBlocks"
                                    ></profile-statuses>
                                </b-card-text>
                            </b-tab>

                            <b-tab title="<?=Loc::getMessage('SP_CI_PROFILE_EDIT_TAB_PROPS');?>">
                                <b-card-text>
                                    <profile-props
                                        :person_type_list="info.site.person_types"
                                        :prop_list="info.site.props"
                                        :field_list="info.crm.fields"
                                        @load_start="startLoadingInfo"
                                        @load_stop="stopLoadingInfo"
                                        @block_update="updateBlocks"
                                    ></profile-props>
                                </b-card-text>
                            </b-tab>

                            <b-tab title="<?=Loc::getMessage('SP_CI_PROFILE_EDIT_TAB_OTHER');?>">
                                <b-card-text>
                                    <profile-other
                                        :prop_other_list="info.site.other_props"
                                        :field_list="info.crm.fields"
                                        @load_start="startLoadingInfo"
                                        @load_stop="stopLoadingInfo"
                                        @block_update="updateBlocks"
                                    ></profile-other>
                                </b-card-text>
                            </b-tab>

                            <b-tab title="<?=Loc::getMessage('SP_CI_PROFILE_EDIT_TAB_NEWORDER');?>">
                                <b-card-text>
                                    <profile-neworder
                                        :categ_list="info.crm.directions"
                                        :condition_list="info.crm.neworder_conds"
                                        :person_type_list="info.site.person_types"
                                        :delivery_type_list="info.site.deliv_types"
                                        :payment_type_list="info.site.pay_types"
                                        :site_buyer_field_list="info.site.buyer_fields"
                                        :crm_contact_field_list="info.crm.contact_fields"
                                        :sites_list="info.site.sites"
                                        @load_start="startLoadingInfo"
                                        @load_stop="stopLoadingInfo"
                                        @block_update="updateBlocks"
                                    ></profile-neworder>
                                </b-card-text>
                            </b-tab>

                        </b-tabs>
                    </div>

                    <profile-info></profile-info>

                    <b-alert show><i class="fa fa-question-circle"></i> <?=Loc::getMessage('SP_CI_PAGE_PROFILE_EDIT_HELP_LINK');?></b-alert>

                    <b-alert show><i class="fa fa-question-circle"></i> <?=Loc::getMessage('SP_CI_PAGE_PROFILE_EDIT_HELP_LINK_2');?></b-alert>

                </div> <!-- end container -->
            </div>

        </div>
    </div>

<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/" . $MODULE_ID . "/admin/include/footer.php" );
