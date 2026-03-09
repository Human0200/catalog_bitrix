<?php
/**
 *    Check module state
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
	Bitrix\Main\Localization\Loc,
    Bitrix\Main\Config\Option;

class CheckState
{
    const MODULE_ID = 'sproduction.integration';
	const SCOPES = ['crm', 'user', 'placement', 'catalog'];

	public static function getErrors() {
		$list = [];
		if (self::isSyncActive()) {
			$errors = [];
			if (!self::checkConnection(true, $errors)) {
				$list[] = \SProdIntegration::formatError('CHECKSTATE_ERROR_CONN',
					Loc::getMessage("SP_CI_CHECKSTATE_ERROR_CONN") . ': ' . implode(', ', $errors));
			}
			else {
				$resp = Rest::execute('scope');
				if (is_array($resp) && !in_array(self::SCOPES, $resp)) {
					$missing_scopes = array_diff(self::SCOPES, $resp);
					if (count($missing_scopes)) {
						$list[] = \SProdIntegration::formatError('CHECKSTATE_ERROR_SCOPE', Loc::getMessage("SP_CI_CHECKSTATE_ERROR_SCOPE", ['#SCOPES#' => implode(', ', $missing_scopes)]), Loc::getMessage("SP_CI_CHECKSTATE_ERROR_SCOPE_HINT"));
					}
				}
			}
		}
		return $list;
	}

	public static function getWarnings() {
		$list = [];
		// Demo-period
		$incl_res = \Bitrix\Main\Loader::includeSharewareModule('sproduction.integration');
		if ($incl_res == \Bitrix\Main\Loader::MODULE_DEMO) {
			$days = \SProdIntegration::getDemoDaysLeft();
			$term_phrase = \SProdIntegration::declineWord($days, Loc::getMessage("SP_CI_WARN_MODULE_DEMO_DAYS_TERM1", ['#DAYS#' => $days]), Loc::getMessage("SP_CI_WARN_MODULE_DEMO_DAYS_TERM2", ['#DAYS#' => $days]), Loc::getMessage("SP_CI_WARN_MODULE_DEMO_DAYS_TERM3", ['#DAYS#' => $days]));
			$list[]['message'] = Loc::getMessage("SP_CI_WARN_MODULE_DEMO_DAYS", ['#TERM_PHRASE#' => $term_phrase]);
		}
		// Updates
		$updates_available_to = 0;
		$last_version = '';
		$updates = CheckUpdates::checkModuleUpdates($updates_available_to, $last_version);
		if (!empty($updates)) {
			$msg_replaces = [
				'#MODULE_ID#' => self::MODULE_ID,
				'#VERSION_CURRENT#' => \SProdIntegration::getModuleInfo()['version'],
				'#VERSION_NEW#' => $last_version,
				'#LANGUAGE_ID#' => LANGUAGE_ID,
			];
			$updates_format = [];
			foreach ($updates as $version => $description) {
				$updates_format[] = $version . ':<br>' . $description;
			}
			if ($updates_available_to > time()) {
				$list[] = [
					'message' => Loc::getMessage("SP_CI_WARN_MODULE_GET_UPDATES", $msg_replaces),
					'hint' => implode("\n", $updates_format),
				];
			}
			else {
				$list[] = [
					'message' => Loc::getMessage("SP_CI_WARN_MODULE_BUY_UPDATES", $msg_replaces),
					'hint' => implode("\n", $updates_format),
				];
			}
		}
		return $list;
	}


	/**
	 * Check system parameters
	 */
	public static function checkList() {
		$res = [
			'auth_file' => false,
			'store_handler_file' => false,
			'crm_handler_file' => false,
			'app_info' => false,
			'auth_info' => false,
			'connect' => false,
			'store_events' => false,
			'crm_events' => false,
			'agents' => false,
			'profiles' => false,
			'crm_events_uncheck' => false,
		];
		// Site base directory
		$site_default = \SProdIntegration::getSiteDef();
		$abs_root_path = $_SERVER['DOCUMENT_ROOT'] . $site_default['DIR'];
		// Check auth file
		if (file_exists($abs_root_path . 'bitrix/sprod_integr_auth.php')) {
			$res['auth_file'] = true;
		}
		// Check handler files
		if (file_exists($abs_root_path . 'bitrix/sprod_integr_bgr_run.php')) {
			$res['store_handler_file'] = true;
		}
		if (file_exists($abs_root_path . 'bitrix/sprod_integr_handler.php')) {
			$res['crm_handler_file'] = true;
		}
		// Availability of B24 application data
		if (Rest::getAppInfo()) {
			$res['app_info'] = true;
			// Availability of connection data
			if (Rest::getAuthInfo()) {
				$res['auth_info'] = true;
			}
		}
		// Check agents
		if (!Settings::get('add_sync_schedule') || AddSync::check()) {
			$res['agents'] = true;
		}
		// Has active profiles
		if (self::checkActiveProfiles()) {
			$res['profiles'] = true;
		}
		if ($res['app_info'] && $res['auth_info']) {
			// Availability of an order change handler
			if (StoreHandlers::check()) {
				$res['store_events'] = true;
			}
			// Relevance of data for connecting to B24
			$resp = Rest::execute('app.info', [], false, true, false);
			if ($resp && !$resp['error']) {
				$res['connect'] = true;
				// Availability of a deal change handler
				if (PortalHandlers::check()) {
					$res['crm_events'] = true;
				}
				if (Settings::get('direction') == 'stoc') {
					$res['crm_events_uncheck'] = true;
				}
			}
		}
		return $res;
	}

	/**
	 * Check active profiles
	 */
	public static function checkActiveProfiles() {
		$is_exist = false;
		$list = ProfilesTable::getList([
			'filter' => ['active' => 'Y'],
			'select' => ['id'],
		]);
		if (count($list)) {
			$is_exist = true;
		}
		return $is_exist;
	}

	public static function checkConnection($conn_test=false, &$errors=[]) {
		$res = false;
		if (Rest::getAppInfo() && Rest::getAuthInfo()) {
			$conn_test_res = true;
			if ($conn_test) {
				$conn_test_res = false;
				$app_info = Rest::execute('app.info', [], false, true, false);
				if ($app_info['result']['ID']) {
					$conn_test_res = true;
				}
				else {
					$errors[] = $app_info['error_description'];
				}
			}
			if ($conn_test_res) {
				$res = true;
			}
		}
		return $res;
	}

	public static function isSyncActive() {
		$result = false;
		if (Settings::get('active')) {
			$result = true;
		}
		return $result;
	}
}
