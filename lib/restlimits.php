<?php
namespace SProduction\Integration;

use Bitrix\Main\Config\Option;

class RestLimits {

	/**
	 * Limits control
	 */
	public static function control($repeat=0) {
		// Waiting for end of executions
		$last_exec_time = self::getLastExecTime();
		$current_exec_time = microtime(true);
		if ($current_exec_time < $last_exec_time) {
			$wait_time = $last_exec_time - $current_exec_time;
			$wait_time = $wait_time * 1000000 + 500000;
			\SProdIntegration::Log('(RestLimits::control) wait ' . $wait_time);
			usleep($wait_time);
			if ($repeat < 10) {
				self::control(++$repeat);
			}
			else {
				$count_exec = self::getCountExec();
				self::setCountExec(++$count_exec);
			}
		}
		else {
			$delay = 0;
			// Update limits
			$count_exec = self::getCountExec();
			$diff_time = $current_exec_time - $last_exec_time;
			$count_exec -= $diff_time * 2;
			$count_exec = max($count_exec, 0);
			$count_exec ++;
			// Calc delay
			if ($count_exec > 30) {
				$diff_time = 1;
				$delay += $diff_time * 1000000;
				$current_exec_time += $diff_time;
				$count_exec -= $diff_time * 1;
			}
			// Save values
			self::setLastExec($current_exec_time);
			self::setCountExec($count_exec);
			// Delay
			if ($delay) {
				\SProdIntegration::Log('(RestLimits::control) delay ' . $delay);
				usleep($delay);
			}
		}
	}

	public static function setLastExec($value) {
		Option::set('main', 'rest_last_exec', $value);
	}

	public static function getLastExecTime() {
		return (float) Option::get('main', 'rest_last_exec');
	}

	public static function setCountExec($value) {
		Option::set('main', 'rest_count_exec', $value);
	}

	public static function getCountExec() {
		return (float) Option::get('main', 'rest_count_exec');
	}

	public static function processQueryLimitError() {
		\SProdIntegration::Log('(RestLimits::processQueryLimitError)');
		self::setCountExec(50);
		self::setLastExec(microtime(true) + 3);
	}

}
