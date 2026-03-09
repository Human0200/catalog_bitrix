<?php
/**
 * Functions for remote monitoring of module state
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

class RemoteMonitor {

	const API_VERSION = 1;

	public static function getInfo(): array {
		$result = [];
		$result['api_version'] = self::API_VERSION;
		// Module status
		$result['status'] = \Bitrix\Main\Loader::includeSharewareModule(\SProdIntegration::MODULE_ID); // 0 - not installed, 1 - installed, 3 - demo expired
		if ($result['status'] > 0) {
			// Version, updates
			$result['current_version'] = CheckUpdates::getCurrentVersion();
			$result['has_updates'] = CheckUpdates::hasUpdates();
			$result['available_updates'] = CheckUpdates::checkUpdatesAvailable();
			$result['last_version'] = CheckUpdates::getLastModuleVersion();
		    // Module state
			$result['state'] = CheckState::checkList();
		    // Errors and warnings
			$result['errors'] = CheckState::getErrors();
			$result['warnings'] = CheckState::getWarnings();
		    // File log link
			$result['filelog_exist'] = FileLogControl::isExist();
			$result['filelog_size'] = FileLogControl::getSize();
			$result['filelog_link'] = FileLogControl::getLink();
		}
		return $result;
	}
}
