<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/interface/admin_lib.php");

CModule::IncludeModule("sale");
CModule::IncludeModule("sproduction.integration");

// Check user permissions
if (!$USER->IsAdmin()) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Access denied',
    ]);
    exit;
}

use SProduction\Integration\Integration,
	SProduction\Integration\Settings,
	SProduction\Integration\PortalData,
	SProduction\Integration\FBasketSync,
	SProduction\Integration\ForgottenBasket,
	SProduction\Integration\FbasketProfilesTable,
    Bitrix\Main\Config\Option,
    Bitrix\Main\Type\DateTime,
    Bitrix\Sale;

Integration::setBulkRun();

$params = json_decode(file_get_contents('php://input'), true);

$next_item     = $params['next_item'] ? $params['next_item'] : 0;
SProdIntegration::Log('(fbasket_sync) next_item '.$next_item);
$step_time = 5;
$start_time = time();

// Get forgotten basket settings
$forgotten_hours = (int) Settings::get('fbasket_hours');
$forgotten_hours = $forgotten_hours > 0 ? $forgotten_hours : 72;

// Calculate date range for forgotten baskets
$date_to = new DateTime();
$date_from = null;
$start_date_ts = PortalData::getStartDateTs();
if ($start_date_ts) {
	$date_from = DateTime::createFromTimestamp($start_date_ts);
	SProdIntegration::Log('(fbasket_sync) using sync start date: ' . $date_from->toString());
}

// Get baskets to sync
$baskets = ForgottenBasket::getList($date_from, $date_to, null, 0, $forgotten_hours, ForgottenBasket::DATE_TYPE_INSERT);
$cnt = count($baskets);

SProdIntegration::Log('(fbasket_sync) found ' . $cnt . ' forgotten baskets to sync');

if ($next_item < $cnt) {
    // Get active profiles for processing
    $profiles = FbasketProfilesTable::getActiveList();
    if (empty($profiles)) {
        SProdIntegration::Log('(fbasket_sync) no active profiles found');
        echo json_encode([
            'status' => 'error',
            'message' => 'No active profiles found',
            'count' => 0,
            'next_item' => 0,
        ]);
        exit;
    }

    // Build profile map by site_id
    $profiles_by_site = [];
    foreach ($profiles as $profile) {
        if ($profile['site']) {
            $profiles_by_site[$profile['site']] = $profile;
        } else {
            $profiles_by_site[''] = $profile;
        }
    }

    $i = $next_item;
    $processed = 0;

    while ($i < $cnt) {
        $exec_time = time() - $start_time;
        if ($exec_time >= $step_time) {
            SProdIntegration::Log('(fbasket_sync) break on '.$i);
            break;
        }

        $basket = $baskets[$i];

        try {
            $result = FBasketSync::processBasket($basket, $profiles_by_site);
            SProdIntegration::Log('(fbasket_sync) processed basket ' . $basket['ID'] . ' with result: ' . $result);
            $processed++;
        } catch (\Exception $e) {
            SProdIntegration::Log('(fbasket_sync) error processing basket ' . $basket['ID'] . ': ' . $e->getMessage());
        }

        $i++;
    }

    $next_item = $i;
}

echo json_encode([
    'status' => 'success',
    'count' => (int)$cnt,
    'next_item' => (int)$next_item,
]);