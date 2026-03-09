<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php" );
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/interface/admin_lib.php");

$MODULE_ID = "sproduction.integration";

CModule::IncludeModule($MODULE_ID);

use \SProduction\Integration\Integration,
	\SProduction\Integration\Rest,
	\SProduction\Integration\BoxMode,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Config\Option,
	\Bitrix\Main\Page\Asset,
	\Bitrix\Sale;
use SProduction\Integration\Settings;

$order_id = false;
if ($_REQUEST['PLACEMENT'] == 'CRM_DEAL_DETAIL_TAB') {
	$data = json_decode($_REQUEST['PLACEMENT_OPTIONS'], true);
	$deal_id = intVal($data['ID']);
	if ($deal_id) {
		$cred = [
			'access_token' => $_REQUEST['AUTH_ID'],
			'refresh_token' => $_REQUEST['REFRESH_ID'],
		];
		if ($USER->IsAuthorized()) {
			// Get order ID
			if (Settings::getSyncMode() == Settings::SYNC_MODE_BOX) {
				$order_id = BoxMode::findOrderByDeal($deal_id);
			}
			else {
				$deal = Rest::execute('crm.deal.get', [
					'id' => $deal_id,
				], $cred);
				if (isset($deal['ID'])) {
					$order_id = (int) $deal[Settings::getOrderIDField()];
				}
			}
		}
	}
}
if (!$order_id) {
	echo 'Not found';
	die();
}

Loc::LoadMessages(__FILE__);
$loc_messages = Loc::loadLanguageFile(__FILE__);

$scripts = [
    '//api.bitrix24.com/api/v1/',
    '/bitrix/js/'.$MODULE_ID.'/page_products_edit.js',
];
$styles = [];
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/" . $MODULE_ID . "/admin/include/header.php" );
?>
    <script>
        order_id = '<?=$order_id;?>';
        messages.ru.page = {
		<?foreach ($loc_messages as $k => $message):?>
		<?=$k;?>: '<?=str_replace(array("\n", "\r"), '', $message);?>',
		<?endforeach;?>
        };
    </script>
    <div id="app">
        <div class="sprod-integr-page" id="sprod_integr_profiles_page">
            <div class="wrapper iframe-wrapper">
                <div class="container-fluid p-3">

                    <main-errors :errors="errors" :warnings="warnings"></main-errors>

                    <div :class="{ 'block-disabled': loader_counter }">

                        <products-filter
                            :filter="filter"
                            :iblock_list="iblock_list"
                            :section_list="section_list"
                            @load_start="startLoadingInfo"
                            @load_stop="stopLoadingInfo"
                            @block_update="updateBlocks"
                        ></products-filter>

                        <products-list
                            :filter="filter"
                            :list="products_list"
                            :fields_all="products_fields_all"
                            :fields_list="products_fields_list"
                            :fields_sel="products_fields_sel"
                            :changed="products_changed"
                            :count="products_count"
                            :page="products_page"
                            @load_start="startLoadingInfo"
                            @load_stop="stopLoadingInfo"
                            @block_update="updateBlocks"
                            @item_add="addItem"
                            @page_change="updateList"
                        ></products-list>

                    </div>

                </div> <!-- end container -->
            </div>

        </div>
    </div>

<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/" . $MODULE_ID . "/admin/include/footer.php" );
