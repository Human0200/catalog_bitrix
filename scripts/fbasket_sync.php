<?php
/**
 * CLI script for forgotten baskets synchronization
 * 
 * Usage: 
 * php fbasket_sync.php
 * 
 * Cron example (every 15 minutes):
 * /usr/bin/php /path/to/bitrix/modules/sproduction.integration/scripts/fbasket_sync.php
 */

// Detect document root
$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__) . '/../../../..');
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

// Define NO_KEEP_STATISTIC to avoid session start
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define('CHK_EVENT', true);

// Include Bitrix prolog
require_once($DOCUMENT_ROOT . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use SProduction\Integration\FBasketSync;

// Check if module is installed
if (!Loader::includeModule('sproduction.integration')) {
    echo "ERROR: Module sproduction.integration is not installed\n";
    exit(1);
}

// Check if sale module is available
if (!Loader::includeModule('sale')) {
    echo "ERROR: Module sale is not installed\n";
    exit(1);
}

// Output start message
echo "[" . date('Y-m-d H:i:s') . "] Starting forgotten baskets synchronization...\n";

try {
    // Run synchronization
    $result = FBasketSync::runSync();
    
    if ($result) {
        echo "[" . date('Y-m-d H:i:s') . "] Synchronization completed successfully\n";
        exit(0);
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Synchronization completed with warnings\n";
        exit(0);
    }
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
