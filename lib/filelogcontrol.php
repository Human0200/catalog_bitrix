<?php
/**
 * File log
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option;

class FileLogControl
{
    const MODULE_ID = 'sproduction.integration';

	public static function reset() {
		$file_path = $_SERVER['DOCUMENT_ROOT'] . Integration::FILELOG;

		return file_put_contents($file_path, '');
	}

	public static function changeStatus($active) {
		Settings::save('filelog', $active ? 'Y' : 'N');
	}

	public static function isExist() {
		$result = false;
		$file_path = $_SERVER['DOCUMENT_ROOT'] . Integration::FILELOG;
		if (file_exists($file_path)) {
			$result = true;
		}

		return $result;
	}

	public static function getSize() {
		$result = false;
		if (self::isExist()) {
			$file_path = $_SERVER['DOCUMENT_ROOT'] . Integration::FILELOG;
			$result = filesize($file_path);
		}

		return $result;
	}

	public static function get() {
		$info = false;
		if (self::isExist()) {
			$info['size'] = self::getSize();
			$info['size_f'] = \SProdIntegration::getFileSizeFormat($info['size']);
		}

		return $info;
	}

	public static function getLink() {
		$link = Integration::getServerAddr() . Integration::FILELOG;

		return $link;
	}
}
