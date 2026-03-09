<?
require_once( $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php" );

$profile_id = intval($_GET['id']);

if ($profile_id) {
	require_once("profile_edit.php");
}
else {
	require_once("profiles_list.php");
}
