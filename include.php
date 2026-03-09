<?
use \Bitrix\Main\Config\Option,
	\Bitrix\Main\Localization\Loc,
	\Bitrix\Main\Page\Asset,
	\SProduction\Integration\Integration,
	\SProduction\Integration\Rest,
	\SProduction\Integration\PortalData,
	\SProduction\Integration\SystemNotify,
	\SProduction\Integration\Settings;
use SProduction\Integration\CheckUpdates;

Loc::loadMessages(__FILE__);

class SProdIntegration
{
	const MODULE_ID = 'sproduction.integration';
	const APP_AUTH_SCRIPT = '/bitrix/sprod_integr_auth.php';

	private static $LOG_LABEL, $MEMORY;

	public static function OnBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu) {
		global $USER, $APPLICATION, $adminMenu, $adminPage;

		if($APPLICATION->GetGroupRight("main") < "R")
			return;

		$aModuleMenu[] = array(
			"parent_menu" => "global_menu_store",
			"sort"        => 300,
			"text"        => Loc::getMessage("SP_CI_MENU_NAME_TEXT"),
			"title"       => Loc::getMessage("SP_CI_MENU_NAME_TITLE"),
			"icon"        => "sprod_integr_icon",
			"page_icon"   => "sprod_integr_icon",
			"items_id"    => "menu_util",
			"items"       => [
				[
					"title" => Loc::getMessage("SP_CI_SETTINGS_TITLE"),
					"text" => Loc::getMessage("SP_CI_SETTINGS_TEXT"),
					"url" => "sprod_integr_settings.php?lang=".LANG,
				],
				[
					"title" => Loc::getMessage("SP_CI_GENERAL_TITLE"),
					"text" => Loc::getMessage("SP_CI_GENERAL_TEXT"),
					"url" => "sprod_integr_general.php?lang=".LANG,
				],
				[
					"title" => Loc::getMessage("SP_CI_PROFILES_TITLE"),
					"text" => Loc::getMessage("SP_CI_PROFILES_TEXT"),
					"url" => "sprod_integr_profiles.php?lang=".LANG,
				],
				[
					"text" => Loc::getMessage("SP_CI_FBASKET_SECTION_TEXT"),
					"title" => Loc::getMessage("SP_CI_FBASKET_SECTION_TITLE"),
					"url" => "sprod_integr_fbasket_settings.php?lang=".LANG,
					"more_url" => [
						"sprod_integr_fbasket_settings.php",
						"sprod_integr_fbasket_profiles.php",
					],
					"items" => [
						[
							"title" => Loc::getMessage("SP_CI_FBASKET_SETTINGS_TITLE"),
							"text" => Loc::getMessage("SP_CI_FBASKET_SETTINGS_TEXT"),
							"url" => "sprod_integr_fbasket_settings.php?lang=".LANG,
						],
						[
							"title" => Loc::getMessage("SP_CI_FBASKET_PROFILES_TITLE"),
							"text" => Loc::getMessage("SP_CI_FBASKET_PROFILES_TEXT"),
							"url" => "sprod_integr_fbasket_profiles.php?lang=".LANG,
							"more_url" => [
								"sprod_integr_fbasket_profile_edit.php",
							],
						],
					],
				],
				[
					"title" => Loc::getMessage("SP_CI_STATUS_TITLE"),
					"text" => Loc::getMessage("SP_CI_STATUS_TEXT"),
					"url" => "sprod_integr_status.php?lang=".LANG,
				],
			],
		);
	}

	public static function appendScriptsToPage() {
		global $APPLICATION;
		if ((defined("ADMIN_SECTION") || (isset($ADMIN_SECTION) && $ADMIN_SECTION === true)) && !$_SERVER['HTTP_BX_AJAX']) {
			if (strpos($_SERVER['REQUEST_URI'], '/bitrix/admin/sprod_integr_') === 0 ||
				strpos($_SERVER['REQUEST_URI'], '/bitrix/admin/sale_order_') === 0) {
				CJSCore::Init('jquery');
				$APPLICATION->AddHeadScript("/bitrix/js/".self::MODULE_ID."/admin_scripts.js");
			}
			if (strpos($_SERVER['REQUEST_URI'], '/bitrix/admin/') === 0) {
				$APPLICATION->SetAdditionalCss("/bitrix/themes/.default/".self::MODULE_ID."/styles.css");
			}
		}
		return false;
	}

	public static function setLogLabel($label='') {
		if ($label) {
			self::$LOG_LABEL = $label;
		}
		elseif (!self::$LOG_LABEL) {
			self::$LOG_LABEL = str_replace('.', '', strval(microtime(true))) . mt_rand(1000, 9999);
		}
	}

	public static function getLogLabel() {
		return self::$LOG_LABEL;
	}

	public static function getLogMemory() {
		$memory = memory_get_usage();
		// Format difference
		$i = 0;
		while (floor($memory / 1024) > 0) {
			$i++;
			$memory /= 1024;
		}
		$name = array('b', 'Kb', 'Mb');
		$memory_format = round($memory, 2) . ' ' . $name[$i];

		return $memory_format;
	}

	public static function getLogMemoryDiff() {
		if (!self::$MEMORY) {
			self::$MEMORY = 0;
		}
		// Get difference
		$memory = memory_get_usage();
		$memory_diff = ($memory - self::$MEMORY);
		self::$MEMORY = $memory;
		// Format difference
		$i = 0;
		while (floor($memory_diff / 1024) > 0) {
			$i++;
			$memory_diff /= 1024;
		}
		$name = array('b', 'Kb', 'Mb');
		$memory_diff_format = round($memory_diff, 2) . ' ' . $name[$i];

		return $memory_diff_format;
	}

	public static function truncLongStrings($string) {
		// Find a sequence of characters without spaces larger than 500 characters
		$pieces = explode(' ', $string);
		foreach ($pieces as $i => $piece) {
			$piece_len = strlen($piece);
			if ($piece_len > 1000) {
				// Shorten a long line
				$pieces[$i] = substr($piece, 0, 100) . ' ... (file size: ' . $piece_len . ') ... ' . substr($piece, -100);
			}
		}
		$result = implode(' ', $pieces);
		return $result;
	}

	public static function Log($string, $trunc=true) {
		self::setLogLabel();
		$log = Option::get(self::MODULE_ID, "filelog");
		if ($log == 'Y') {
			// Shorten long strings
			if ($trunc) {
				$string = self::truncLongStrings($string);
			}
			// Get memory
//			$memory = self::getLogMemory();
//			$memory_diff = self::getLogMemoryDiff();
			// Write record to log
			file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/upload/sprod_integr_log.txt', $string, FILE_APPEND | LOCK_EX);
			$info = date('d.m.Y H:i:s');
			$info .= ' label ' . self::getLogLabel();
//			$info .= ' memory ' . $memory;
//			$info .= ' memory_diff ' . $memory_diff;
			file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/upload/sprod_integr_log.txt',  "\n---\n" . $info . "\n\n", FILE_APPEND | LOCK_EX);
		}
	}

	public static function isUtf() {
		return defined('BX_UTF') && BX_UTF === true;
	}

	public static function mainCheck() {
		$errors = [];
		$incl_res = \Bitrix\Main\Loader::includeSharewareModule('sproduction.integration');
		if ($incl_res == \Bitrix\Main\Loader::MODULE_NOT_FOUND) {
			$errors[] = self::formatError('MODULE_NOT_FOUND', Loc::getMessage("SP_CI_WARN_MODULE_NOT_FOUND"));
		}
		elseif ($incl_res == \Bitrix\Main\Loader::MODULE_DEMO_EXPIRED) {
			$errors[] = self::formatError('MODULE_DEMO_EXPIRED', Loc::getMessage("SP_CI_WARN_MODULE_DEMO_EXPIRED"));
		}
		if (!function_exists('curl_version')) {
			$errors[] = self::formatError('ERROR_CURL', Loc::getMessage("SP_CI_ERROR_CURL"));
		}
		return $errors;
	}

	public static function formatError($code, $message, $hint='') {
		return [
			'code' => $code,
			'message' => $message,
			'hint' => $hint,
		];
	}

	public static function getSiteDef() {
		$site_default = false;
		$result = Bitrix\Main\SiteTable::getList([]);
		while ($site = $result->fetch()) {
			if (!$site_default) {
				$site_default = $site;
			}
			if ($site['DEF'] == 'Y') {
				$site_default = $site;
			}
		}
		return $site_default;
	}

	/**
	 * Utilites
	 */

	public static function declineWord($num=0, $im, $rod_ed, $rod_mn) {
		if ($num > 10 && ((int)(($num % 100) - ($num % 10)) / 10) == 1) {
			return $rod_mn;
		}
		else {
			switch($num % 10) {
				case (1) : return $im; break;
				case (2) :
				case (3) :
				case (4) : return $rod_ed; break;
				case (5) :
				case (6) :
				case (7) :
				case (8) :
				case (9) :
				case (0) : return $rod_mn; break;
				default: return $im;
			}
		}
	}

	public static function getFileSizeFormat($size){
		$a = [
			Loc::getMessage("SP_CI_FILE_SIZE_FORMAT_B"),
			Loc::getMessage("SP_CI_FILE_SIZE_FORMAT_KB"),
			Loc::getMessage("SP_CI_FILE_SIZE_FORMAT_MB"),
			Loc::getMessage("SP_CI_FILE_SIZE_FORMAT_GB"),
			Loc::getMessage("SP_CI_FILE_SIZE_FORMAT_TB"),
			Loc::getMessage("SP_CI_FILE_SIZE_FORMAT_PB"),
		];
		for ($i = 0; $size > 1024; $i++){
			$size /= 1024;
		}
		return round($size).' '.$a[$i];
	}

	public static function getDemoDaysLeft() {
		$left_days = false;
		$incl_res = \Bitrix\Main\Loader::includeSharewareModule('sproduction.integration');
		if ($incl_res == \Bitrix\Main\Loader::MODULE_DEMO) {
			$time_cur = time();
			$module_const = 'sproduction_integration_OLDSITEEXPIREDATE';
			if(defined($module_const) && $time_cur < constant($module_const)) {
				$expire_info = getdate(constant($module_const));
				$now_info = getdate($time_cur);
				$expire_ts = gmmktime($expire_info['hours'], $expire_info['minutes'], $expire_info['seconds'], $expire_info['mon'], $expire_info['mday'], $expire_info['year']);
				$new_ts = gmmktime($expire_info['hours'], $expire_info['minutes'], $expire_info['seconds'], $now_info['mon'], $now_info['mday'], $now_info['year']);
				$left_days = ($expire_ts - $new_ts) / 86400;
			}
		}
		return $left_days;
	}

	public static function getModuleInfo() {
		$arModuleVersion = [];
		include(dirname(__FILE__) . '/install/version.php');
		return [
			'version' => $arModuleVersion["VERSION"] ?? '',
			'version_date' => $arModuleVersion["VERSION_DATE"] ?? '',
		];
	}

	public static function getUpdateInfo(){
		$result = false;
		include_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/classes/general/update_client_partner.php');
		if (class_exists('\CUpdateClientPartner')) {
			$modules_id = [self::MODULE_ID];
			$error = '';
			$upd_list = \CUpdateClientPartner::getUpdatesList($error, LANGUAGE_ID, 'Y', $modules_id, ['fullmoduleinfo'=>'Y']);
			if (is_array($upd_list)) {
				foreach ($upd_list['MODULE'] as $item) {
					if ($item['@']['ID'] == self::MODULE_ID) {
						$result = $item['@'];
					}
				}
			}
		}
		return $result;
	}

	public static function isSyncAllow($date_create=false) {
		$result = true;
		$incl_res = \Bitrix\Main\Loader::includeSharewareModule(Integration::MODULE_ID);
		if ($incl_res == \Bitrix\Main\Loader::MODULE_NOT_FOUND || $incl_res == \Bitrix\Main\Loader::MODULE_DEMO_EXPIRED) {
			$result = false;
		}
		if ( ! Rest::checkConnection()) {
			$result = false;
		}
		// Check module active
		$sync_active = Settings::get("active");
		if ( ! $sync_active) {
			$result = false;
		}
		// Check start date
		if ($date_create) {
			$start_date_ts = PortalData::getStartDateTs();
			if ($start_date_ts && $date_create < $start_date_ts) {
				$result = false;
			}
		}
		return $result;
	}

}
?>