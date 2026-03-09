<?php
/**
 *    Rest
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option,
	SProduction\Integration\Settings,
	Bitrix\Main\Application;

class Rest
{
    const MODULE_ID = 'sproduction.integration';
	protected static $MANUAL_RUN = false;
	protected static $STORE_EVENTS_BGR = true;
    static $site = false;
    static $portal = false;
    static $app_id = false;
    static $secret = false;

	/**
	 * Status of running events by asinchronous mode
	 */

	public static function disableStoreEventsBgrRun() {
		self::$STORE_EVENTS_BGR = false;
	}

	public static function isStoreEventsBgrRunEnabled() {
		//\SProdIntegration::Log('(Rest::isStoreEventsBgrRunEnabled) status ' . (self::$STORE_EVENTS_BGR ? 'true' : 'false'));
		return self::$STORE_EVENTS_BGR;
	}

	/**
	 * Bulk run indicator
	 */

	public static function setBulkRun() {
		self::$MANUAL_RUN = true;
	}

	public static function isBulkRun() {
		return self::$MANUAL_RUN;
	}

	/**
	 * Get Bitrix24 application info
	 */

    public static function getAppInfo() {
		$info = false;
	    if (self::$site === false) {
		    self::$site = Settings::get("site");
	    }
	    if (self::$portal === false) {
		    self::$portal = Settings::get("portal");
	    }
	    if (self::$app_id === false) {
		    self::$app_id = Settings::get("app_id");
	    }
	    if (self::$secret === false) {
		    self::$secret = Settings::get("secret");
	    }
	    if (self::$site && self::$portal && self::$app_id && self::$secret) {
		    $info = [
		    	'site' => self::$site,
		    	'portal' => self::$portal,
		    	'app_id' => self::$app_id,
		    	'secret' => self::$secret,
		    ];
	    }
		return $info;
    }

	/**
	 * Save file with auth data
	 *
	 * @param $info
	 *
	 * @return bool|int
	 */

	public static function saveAuthInfo($info) {
		$res = Settings::save("credentials", $info, is_array($info));
		return $res;
	}

	/**
	 * Read auth data
	 *
	 * @return bool|mixed
	 */

	public static function getAuthInfo() {
		$info = Settings::get("credentials", true);
		return $info;
	}

	/**
	 * Get link for application authentication
	 *
	 * @return bool|string
	 */
	public static function getAuthLink() {
		$app_info = self::getAppInfo();
		if (!$app_info) {
			return false;
		}
		$link = $app_info['portal'].'/oauth/authorize/?client_id='.$app_info['app_id'].'&response_type=code';
		return $link;
	}

	/**
     * Get auth token
     */

	public static function restToken($code) {
	    $app_info = self::getAppInfo();
		\SProdIntegration::Log('(restToken) code ' . $code);
        if (!$code || !$app_info) {
            return false;
        }

        $query_url = 'https://oauth.bitrix.info/oauth/token/';
        $query_data = http_build_query([
	        'grant_type' => 'authorization_code',
	        'client_id' => $app_info['app_id'],
	        'client_secret' => $app_info['secret'],
	        'code' => $code,
        ]);
		if (Settings::get('log_queries') == 'Y') {
			\SProdIntegration::Log('(restToken) query ' . $query_url . '?' . $query_data, false);
		}
        $curl = curl_init();
		$curl_opts = [
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => $query_url . '?' . $query_data,
		];
		$proxy = Proxy::getRandom();
		if ($proxy) {
			$curl_opts[CURLOPT_PROXYTYPE] = 'HTTP';
			$curl_opts[CURLOPT_PROXY] = $proxy['ip'];
			$curl_opts[CURLOPT_PROXYPORT] = $proxy['port'];
			if ($proxy['login'] && $proxy['password']) {
				$curl_opts[CURLOPT_PROXYUSERPWD] = $proxy['login'] . ':' . $proxy['password'];
			}
		}
		curl_setopt_array($curl, $curl_opts);
        $result = curl_exec($curl);
        curl_close($curl);
        $cred = json_decode($result, true);
		\SProdIntegration::Log('(restToken) result ' . print_r($cred, true));

        if (!$cred['error']) {
            // Save new auth credentials
            self::saveAuthInfo($cred);
        }

        return $cred;
    }


    /**
     * Refresh access token
     *
     * @param array $refresh_token
     * @return bool|mixed
     */

	public static function refreshToken($refresh_token) {
    	$app_info = self::getAppInfo();
        if (!isset($refresh_token) || !$app_info) {
            return false;
        }

		RestLimits::control();

        $query_url = 'https://oauth.bitrix.info/oauth/token/';
        $query_data = http_build_query([
	        'grant_type' => 'refresh_token',
	        'client_id' => $app_info['app_id'],
	        'client_secret' => $app_info['secret'],
	        'refresh_token' => $refresh_token,
        ]);
		if (Settings::get('log_queries') == 'Y') {
			\SProdIntegration::Log('(refreshToken) query ' . $query_url . '?' . $query_data, false);
		}
        $curl = curl_init();
		$curl_opts = [
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => $query_url . '?' . $query_data,
		];
		$proxy = Proxy::getRandom();
		if ($proxy) {
			$curl_opts[CURLOPT_PROXYTYPE] = 'HTTP';
			$curl_opts[CURLOPT_PROXY] = $proxy['ip'];
			$curl_opts[CURLOPT_PROXYPORT] = $proxy['port'];
			if ($proxy['login'] && $proxy['password']) {
				$curl_opts[CURLOPT_PROXYUSERPWD] = $proxy['login'] . ':' . $proxy['password'];
			}
		}
		curl_setopt_array($curl, $curl_opts);
        $result = curl_exec($curl);
        curl_close($curl);
	    $resp = json_decode($result, true);

		if (isset($resp['error'])) {
			throw new Exception($resp['error_description'], $resp['error']);
		}

        return $resp;
    }


    /**
     * Send rest query to Bitrix24.
     *
     * @param $method - Rest method, ex: methods
     * @param array $params - Method params, ex: []
     * @param array $cred - Authorize data, ex: Array('domain' => 'https://test.bitrix24.com', 'access_token' => '7inpwszbuu8vnwr5jmabqa467rqur7u6')
     * @param boolean $auth_refresh - If authorize is expired, refresh token
     *
     * @return mixed
     */

    public static function execute($method, array $params = [], $cred = false, $auth_refresh = true, $only_res=true, $err_repeate=true, $force_cache = false) {
	    // Get from cache
	    if (RestCache::hasValue($method, $params, $only_res)) {
		    \SProdIntegration::Log('(Rest::execute) get method ' . $method . ' cache');
		    return RestCache::get($method, $params, $only_res);
	    }
	    \SProdIntegration::Log('(rest execute) method ' . $method);

	    $app_info = self::getAppInfo();
	    if (!$app_info) {
		    return false;
	    }
	    if (!$cred) {
		    $cred = self::getAuthInfo();
	    }

	    RestLimits::control();

	    // Command to the REST server
	    $access_token = $cred['access_token'] ?? '';
	    $query_url = $app_info['portal'] . '/rest/' . $method;
	    $query_data = http_build_query(array_merge($params, ['auth' => $access_token]));
	    if (Settings::get('log_queries') == 'Y') {
		    \SProdIntegration::Log('(rest execute) query ' . $query_url . '?' . $query_data, false);
	    }
	    $curl = curl_init();
	    $curl_opts = [
		    CURLOPT_POST           => true,
		    CURLOPT_HEADER         => false,
		    CURLOPT_RETURNTRANSFER => true,
		    CURLOPT_SSL_VERIFYPEER => true,
		    CURLOPT_URL            => $query_url,
		    CURLOPT_POSTFIELDS     => $query_data,
	    ];
		$proxy = Proxy::getRandom();
		if ($proxy) {
			$curl_opts[CURLOPT_PROXYTYPE] = 'HTTP';
			$curl_opts[CURLOPT_PROXY] = $proxy['ip'];
			$curl_opts[CURLOPT_PROXYPORT] = $proxy['port'];
			if ($proxy['login'] && $proxy['password']) {
				$curl_opts[CURLOPT_PROXYUSERPWD] = $proxy['login'] . ':' . $proxy['password'];
			}
		}
	    curl_setopt_array($curl, $curl_opts);
	    $result = curl_exec($curl);
	    curl_close($curl);
	    $resp = json_decode($result, true);

	    // Error to the log
	    if (isset($resp['error']) || isset($resp['error_description'])) {
		    \SProdIntegration::Log('(rest execute) query "' . $method . '" error: ' . $resp['error_description'] . ' [' . $resp['error'] . ']');
		    // If token expired then refresh it
	        if (in_array($resp['error'], ['expired_token', 'invalid_token'])) {
			    if ($auth_refresh) {
				    // Try to get new access token
				    $i = 0;
				    do {
					    if ($i > 0) {
						    sleep(1);
					    }
					    try {
						    $cred = self::refreshToken($cred['refresh_token']);
					    } catch (\Exception $e) {
						    \SProdIntegration::Log('(rest execute) query "' . $method . '" refresh token error: ' . $e->getMessage() . ' [' . $e->getCode() . ']');
					    }
					    $i ++;
				    } while ( ! $cred["access_token"] && $i <= 3);
				    if (is_array($cred)) {
					    foreach ($cred as $k => $value) {
						    $cred_log[$k] = mb_strimwidth($value, 0, 8, '***');
					    }
				    }
				    \SProdIntegration::Log('(rest execute) query "' . $method . '" repeat result: ' . print_r($cred_log, true));
				    if ($cred["access_token"]) {
					    // Save new auth credentials
					    self::saveAuthInfo($cred);
					    // Execute again
					    $resp = self::execute($method, $params, $cred, false, false);
				    }
			    }
		    } // Other errors
		    else {
			    // Query limit
		        if (in_array($resp['error'], ['QUERY_LIMIT_EXCEEDED'])) {
					RestLimits::processQueryLimitError();
		        }
			    if ($err_repeate) {
				    $i = 0;
					while ((($resp['error'] ?? false) || ($resp['error_description'] ?? false)) && $i < 2) {
					    usleep(500000);
					    // Execute again
					    try {
						    $resp = self::execute($method, $params, $cred, $auth_refresh, false, false);
					    } catch (\Exception $e) {
						    \SProdIntegration::Log('(rest execute) query "' . $method . '" repeat error: ' . $e->getMessage() . ' [' . $e->getCode() . ']');
					    }
					    $i ++;
				    }
			    }
			    // Return exception
			    if (($resp['error'] ?? false) || ($resp['error_description'] ?? false)) {
				    \SProdIntegration::Log('(rest execute) query "' . $method . '" exception');
				    throw new Exception($resp['error_description'], $resp['error']);
			    }
		    }
	    }

        // Get results
        if ($only_res) {
	        $result = is_array($resp) ? $resp['result'] : false;
        }
        else {
	        $result = $resp;
        }

	    // Save cache
	    if (!isset($resp['error']) && !isset($resp['error_description'])) {
		    RestCache::add($method, $params, $only_res, $result, $force_cache);
	    }

		// Wakeup DB for long process
        if (self::isBulkRun()) {
			self::updateDBConnection();
        }

        return $result;
    }

	public static function executeGetFlat($method, array $params = [], $cred = false) {
		\SProdIntegration::Log('(rest execute) method ' . $method);
		$app_info = self::getAppInfo();
		if ( ! $app_info) {
			return false;
		}
		if ( ! $cred) {
			$cred = self::getAuthInfo();
		}
		// Command to the REST server
		$query_url = $app_info['portal'] . '/rest/' . $method;
		$query_data = http_build_query(array_merge($params, ['auth' => $cred["access_token"]]));
		return $query_url . '?' . $query_data;
	}


	/**
	 * Batch request
	 */

	public static function batch(array $req_list, $cred = false) {
		$result = [];
		// Info for log
		$methods = [];
		foreach ($req_list as $item) {
			$methods[] = $item['method'];
		}
		\SProdIntegration::Log('(Rest::batch) methods ' . implode(', ', $methods));
		if (!empty($req_list)) {
			$req_limit = 50;
			$req_count  = ceil(count($req_list) / $req_limit);
			for ($i = 0; $i < $req_count; $i ++) {
				$req_list_f = [];
				$j = 0;
				foreach ($req_list as $id => $item) {
					if ($j >= $i * $req_limit && $j < ($i + 1) * $req_limit) {
						$params          = isset($item['params']) ? http_build_query($item['params']) : '';
						$req_list_f[$id] = $item['method'] . '?' . $params;
					}
					$j++;
				}
				if ( ! empty($req_list_f)) {
					$resp   = self::execute('batch', [
						"halt" => false,
						"cmd"  => $req_list_f,
					], $cred);
					$result = array_merge($result, $resp['result'] ?? []);
				}
			}
		}
		return $result;
	}


	/**
	 * Universal list
	 */

	public static function getList($method, $sub_array='', $params=[], $limit=0) {
		$list = [];
		$resp = self::execute($method, $params, false, true, false);
		$count = $resp['total'];
		if ($count) {
			$req_list = [];
			$req_count = ceil($count / 50);
			// List without counting
			if (isset($params['order']['id']) && $params['order']['id'] == 'asc') {
				$params['start'] = - 1;
				$params['count_total'] = 'N';
				for ($i = 0; $i < $req_count; $i ++) {
					$last_result = '$result[' . ($i - 1) . ']';
					if ($sub_array) {
						$last_result .= '[' . $sub_array . ']';
					}
					$last_result .= '[49][id]';
					$params['filter']['>id'] = $i ? $last_result : 0;
					$req_list[$i] = [
						'method' => $method,
						'params' => $params,
					];
				}
			} // List with counting
			else {
				for ($i=0; $i<$req_count; $i++) {
					$next = $i * 50;
					$params['start'] = $next;
					$req_list[$i] = [
						'method' => $method,
						'params' => $params,
					];
				}
			}
			$resp = self::batch($req_list);
			foreach ($resp as $step_list) {
				if ($sub_array) {
					$step_list = $step_list[$sub_array];
				}
				if (is_array($step_list)) {
					foreach ($step_list as $item) {
						if ( ! $limit || $i < $limit) {
							$list[] = $item;
							$i ++;
						}
					}
				}
			}
		}
		return $list;
	}

	public static function getBgrRequestSecret() {
		$secret = Settings::get('bgr_request_secret');
		if (!$secret) {
			$secret = md5(time());
			Settings::save('bgr_request_secret', $secret);
		}
		return $secret;
	}

	/**
	 * Send request on background
	 */

	public static function sendBgrRequest($uri, $data) {
		$success = false;
		$data['secret_key'] = self::getBgrRequestSecret();
		$data['log_label'] = \SProdIntegration::getLogLabel();
		$app_info = self::getAppInfo();
		$site = $app_info['site'];
		$url_info = parse_url($site);
		$is_https = $url_info['scheme'] == 'https' ? true : false;
		$server = $url_info['host'];
		$query_url = ($is_https ? 'https://' : 'http://') . $server . $uri;
		$query_data = http_build_query($data);
		if ($server) {
			$try_limit = Settings::get('bgr_req_repeat_cnt') ? : 3;
			$timeout = 1;
			for ($i=1; $i<=$try_limit && !$success; $i++) {
				$curl = curl_init();
				$curl_opts = [
					CURLOPT_POST           => true,
					CURLOPT_HEADER         => false,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_URL            => $query_url,
					CURLOPT_POSTFIELDS     => $query_data,
					CURLOPT_FRESH_CONNECT  => true,
				];
				if (self::isStoreEventsBgrRunEnabled()) {
					$curl_opts[CURLOPT_TIMEOUT] = $timeout;
				}
				curl_setopt_array($curl, $curl_opts);
				curl_exec($curl);
				$info = curl_getinfo($curl);
				$resp_code = (int)$info['http_code'];
				$bgr_req_null_access = (Settings::get('bgr_req_null_access') == 'Y');
				$success = $resp_code < 300 && ($bgr_req_null_access || ($info['total_time'] > $timeout) || $resp_code > 0);
				\SProdIntegration::Log('(Rest::sendBgrRequest) response ' . (!$success ? 'failure' : 'success'));
				if (!$success) {
//		            \SProdIntegration::Log('(Rest::sendBgrRequest) query ' . $query_url . '?' . $query_data, true);
					\SProdIntegration::Log('(Rest::sendBgrRequest) info ' . print_r($info, true));
				}
				curl_close($curl);
			}
		}
		return $success;
	}

	public static function checkConnection() {
		$res = false;
		if (Rest::getAppInfo() && Rest::getAuthInfo()) {
			$res = true;
		}
		return $res;
	}

	public static function updateDBConnection() {
		$config = Application::getConnection()->getConfiguration();
		$link = mysqli_connect($config['host'], $config['login'], $config['password'], $config['database']);
		if ($link) {
			mysqli_close($link);
		}
	}

}