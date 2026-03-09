<?php
/**
 * Syncronization orders with CRM Bitrix24
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

class BgrRunLock
{
	const MODULE_ID = 'sproduction.integration';
	private static $LOG_LABEL;

	public static function getLockFilePath() {
		return $_SERVER['DOCUMENT_ROOT'] . '/upload/sprod_integr_bgr.lock';
	}

	public static function isBgrRunLock() {
		$result = false;
		$lock_file = self::getLockFilePath();
		if (file_exists($lock_file)) {
//			BgrRunLock::saveLog('(BgrRunLock) locked');
			$result = true;
		}
		return $result;
	}

	public static function setBgrRunLock() {
		$lock_file = self::getLockFilePath();
		if (!file_exists($lock_file)) {
//			BgrRunLock::saveLog('(BgrRunLock) set');
			file_put_contents($lock_file, '');
		}
	}

	public static function clearBgrRunLock() {
		$lock_file = self::getLockFilePath();
		if (file_exists($lock_file)) {
//			BgrRunLock::saveLog('(BgrRunLock) clear');
			unlink($lock_file);
		}
	}

	public static function setLogLabel() {
		if (!self::$LOG_LABEL) {
			self::$LOG_LABEL = str_replace('.', '', strval(microtime(true)));
		}
	}

	public static function saveLog($string) {
		self::setLogLabel();
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/upload/sprod_integr_log.txt', $string, FILE_APPEND);
		$info = date('d.m.Y H:i:s');
		$info .= ' label ' . self::$LOG_LABEL;
		file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/upload/sprod_integr_log.txt',  "\n---\n" . $info . "\n\n", FILE_APPEND);
	}

}