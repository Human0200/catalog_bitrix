<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$MODULE_ID = "sproduction.integration";

CModule::IncludeModule("sproduction.integration");
CModule::IncludeModule("sale");
CModule::IncludeModule("crm");

use \Bitrix\Main\Application,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Sale,
	\SProduction\Integration\Rest,
	\SProduction\Integration\Settings,
	\SProduction\Integration\Secure,
	\SProduction\Integration\StoreData,
	\SProduction\Integration\PortalData,
	\SProduction\Integration\BoxMode,
	\SProduction\Integration\PortalCustomFields;

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
$local_app = Rest::getAppInfo();
$cred = [
	'access_token' => $_REQUEST['AUTH_ID'],
	'refresh_token' => $_REQUEST['REFRESH_ID'],
];
$remote_app = Rest::execute('app.info', [], $cred);
if ($local_app['app_id'] != $remote_app['CODE']) {
	return;
}

$p_options = json_decode($_REQUEST['PLACEMENT_OPTIONS'], true);
$deal_id = intval($p_options['ENTITY_VALUE_ID']);
$field_code = intval($p_options['FIELD_NAME']);

// Get order ID
$order_id = false;
if ($_REQUEST['PLACEMENT'] == 'USERFIELD_TYPE' && $p_options['ENTITY_ID'] == 'CRM_DEAL') {
	if (Settings::getSyncMode() == Settings::SYNC_MODE_BOX) {
		$order_id = BoxMode::findOrderByDeal($deal_id);
	}
	else {
        $d_fields = Rest::execute('crm.deal.fields');
        $field_info = $d_fields[$p_options['FIELD_NAME']];
        if (strpos($field_info['type'], PortalCustomFields::getFieldCode('sprodintegrorderuser')) !== false) {
	        $deals = PortalData::getDeal([$deal_id]);
	        if ( ! empty($deals)) {
		        $deal = $deals[0];
		        $source_id = Settings::get("source_id");
		        if ( ! $source_id || $source_id == $deal['ORIGINATOR_ID']) {
			        $order_id = (int) $deal[Settings::getOrderIDField()];
		        }
	        }
        }
	}
}

if (!$order_id) {
	die();
}

// Order data
$order      = Sale\Order::load($order_id);
$order_data = StoreData::getOrderInfo($order);
$order_user_id = $order_data['USER_ID'];

// --- DISPLAY PAGE --- //

Loc::LoadMessages(__FILE__);
$loc_messages = Loc::loadLanguageFile(__FILE__);

$scripts = [
	'//api.bitrix24.com/api/v1/',
	'/bitrix/js/'.$MODULE_ID.'/page_custom_fields.js',
];
$styles = [];
$BODY_CLASS = 'custom-fields';
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/" . $MODULE_ID . "/admin/include/header.php" );
?>
    <script>
        order_id = '<?=$order_id;?>';
        secure_code = '<?=Secure::getAjaxSCode();?>';
        messages.ru.page = {
			<?foreach ($loc_messages as $k => $message):?>
			<?=$k;?>: '<?=str_replace(array("\n", "\r"), '', $message);?>',
			<?endforeach;?>
        };
    </script>
    <div id="app">
        <div class="sprod-integr-page" id="sprod_integr_custom_fields">
            <div class="wrapper iframe-wrapper">
                <div class="container-fluid p-3">

                    <main-errors :errors="errors" :warnings="warnings"></main-errors>

                    <div :class="{ 'block-disabled': loader_counter }">

                        <uf-orderuser
                                :order_id="<?=$order_id;?>"
                                :user_id="<?=$order_user_id;?>"
                                @load_start="startLoadingInfo"
                                @load_stop="stopLoadingInfo"
                        ></uf-orderuser>

                    </div>

                </div> <!-- end container -->
            </div>

        </div>
    </div>

<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/" . $MODULE_ID . "/admin/include/footer.php" );
