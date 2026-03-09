<?php
/*
 * Remote monitoring API
 */

error_reporting( E_ERROR );

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

\Bitrix\Main\Loader::includeModule('sproduction.integration');

use \SProduction\Integration\RemoteMonitorAccess,
	\SProduction\Integration\RemoteMonitor;

if (RemoteMonitorAccess::isActive() && $_REQUEST['k'] && RemoteMonitorAccess::checkAccess($_REQUEST['k'])) {
	echo \Bitrix\Main\Web\Json::encode(RemoteMonitor::getInfo());
}
