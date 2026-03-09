<?php

namespace SProduction\Integration;

use Bitrix\Main,
	Bitrix\Main\Localization\Loc;

class CheckUpdates {

	const UPDATE_INTERVAL = 86400; // 24*60*60

	/**
	 * Get available updates of all modules
	 */
	public static function getNotifyTag($module_id) {
		return \SProdIntegration::MODULE_ID . '_update_for_' . $module_id;
	}

	/**
	 *	Delete update notification after update is installed
	 */
	public static function onModuleUpdate($modules) {
		if (is_array($modules)) {
			foreach ($modules as $module_id) {
				SystemNotify::deleteNotify(CheckUpdates::getNotifyTag($module_id));
			}
		}
	}

	/**
	 *	Auto check updates
	 */
	public static function onAfterEpilog() {
		if (defined('ADMIN_SECTION') && is_object($GLOBALS['USER']) && $GLOBALS['USER']->isAdmin()) {
			$is_ajax = \Bitrix\Main\Application::GetInstance()->getContext()->getRequest()->isAjaxRequest();
			$is_post = \Bitrix\Main\Application::GetInstance()->getContext()->getRequest()->isPost();
			if ( ! $is_ajax && ! $is_post) {
				$exclude_pages = [
					'/bitrix/admin/update_system_partner_act.php',
					'/bitrix/admin/update_system_act.php',
					'/bitrix/admin/bitrix/admin/update_system_partner.php',
					'/bitrix/admin/update_system_partner_call.php',
					'/bitrix/admin/update_system.php',
					'/bitrix/admin/update_system_call.php',
					'/bitrix/admin/sale_print.php',
				];
				$url = $GLOBALS['APPLICATION']->getCurPage(false);
				if ( ! in_array($url, $exclude_pages)) {
					if (Settings::get('check_updates_block') != 'Y') {
						$last_time_check = Settings::get('check_updates_last_time');
						if ( ! is_numeric($last_time_check) || $last_time_check <= 0) {
							$last_time_check = 0;
						}
						if ( ! $last_time_check || (time() - $last_time_check >= static::UPDATE_INTERVAL)) {
							self::checkAndNotify();
						}
					}
				}
			}
		}
	}

	/**
	 * Get current version of module
	 */
	public static function getCurrentVersion() {
		return \SProdIntegration::getModuleInfo()['version'];
	}

	/**
	 * Has new updates
	 */
	public static function hasUpdates() {
		$result = false;
		$updates_available_to = 0;
		$last_version = '';
		$updates = CheckUpdates::checkModuleUpdates($updates_available_to, $last_version);
		if (!empty($updates) && $updates_available_to > time()) {
			$result = true;
		}
		return $result;
	}

	/**
	 * Check updates available
	 */
	public static function checkUpdatesAvailable() {
		$result = false;
		$updates_available_to = 0;
		$last_version = '';
		CheckUpdates::checkModuleUpdates($updates_available_to, $last_version);
		if ($updates_available_to > time()) {
			$result = true;
		}
		return $result;
	}

	/**
	 * Get last version of module
	 */
	public static function getLastModuleVersion() {
		$updates_available_to = 0;
		$last_version = '';
		$updates = CheckUpdates::checkModuleUpdates($updates_available_to, $last_version);
		if (!empty($updates)) {
			$result = $last_version;
		}
		else {
			$result = self::getCurrentVersion();
		}
		return $result;
	}

	/**
	 * Get available updates of all modules
	 */
	public static function getAllAvailableUpdates() {
		$result = [];
		include_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/classes/general/update_client_partner.php');
		if (class_exists('\CUpdateClientPartner')) {
			$modules_ids = [\SProdIntegration::MODULE_ID];
			$error = null;
			$result = \CUpdateClientPartner::getUpdatesList($error, LANGUAGE_ID, 'Y', $modules_ids, ['fullmoduleinfo' => 'Y']);
			if ( ! is_array($result)) {
				$result = [];
			}
		}

		return $result;
	}

	/**
	 *	Check module updates
	 */
	public static function checkModuleUpdates(int &$updates_available_to, string &$last_version) {
		$available_updates = [];
		$all_updates = CheckUpdates::getAllAvailableUpdates();
		if (is_array($all_updates) && is_array($all_updates['MODULE'])) {
			foreach ($all_updates['MODULE'] as $module_data) {
				if ($module_data['@']['ID'] == \SProdIntegration::MODULE_ID) {
					if (preg_match('#^(\d{1,2})\.(\d{1,2})\.(\d{4})$#', $module_data['@']['DATE_TO'], $match)) {
						$updates_available_to = mktime(23, 59, 59, $match[2], $match[1], $match[3]);
					}
					if (is_array($module_data['#']) && is_array($module_data['#']['VERSION'])) {
						foreach ($module_data['#']['VERSION'] as $version) {
							$available_updates[$version['@']['ID']] = $version['#']['DESCRIPTION'][0]['#'];
							$last_version = $version['@']['ID'];
						}
					}
				}
			}
		}

		return $available_updates;
	}

	/**
	 *	Check all updates
	 */
	public static function checkAndNotify() {
		// Delete previous notify messages
		$strCompare = static::getNotifyTag('');
		foreach (SystemNotify::getNotifyList() as $item) {
			if (stripos($item['TAG'], $strCompare) === 0) {
				SystemNotify::deleteNotify($item['TAG']);
			}
		}
		// Check new updates
		$updates_available_to = 0;
		$last_version = '';
		$updates = CheckUpdates::checkModuleUpdates($updates_available_to, $last_version);
		if (!empty($updates)) {
			$msg_replaces = [
				'#MODULE_ID#'       => \SProdIntegration::MODULE_ID,
				'#VERSION_CURRENT#' => self::getCurrentVersion(),
				'#VERSION_NEW#'     => $last_version,
				'#LANGUAGE_ID#'     => LANGUAGE_ID,
			];
			if ($updates_available_to > time()) {
				$message = Loc::getMessage("SP_CI_CHECKUPDATES_GET_UPDATES", $msg_replaces);
			} else {
				$message = Loc::getMessage("SP_CI_CHECKUPDATES_BUY_UPDATES", $msg_replaces);
			}
			SystemNotify::addNotify($message, static::getNotifyTag(\SProdIntegration::MODULE_ID));
		}
		Settings::save('check_updates_last_time', time());

		return true;
	}

}
