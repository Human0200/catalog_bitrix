<?php
/**
 * Event handlers of portal
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
	Bitrix\Main\Type,
	Bitrix\Main\Entity,
	Bitrix\Main\Localization\Loc,
	Bitrix\Main\SiteTable,
	Bitrix\Sale;

Loc::loadMessages(__FILE__);

class PortalHandlers
{
    const MODULE_ID = 'sproduction.integration';
	public const EVENTS_HANDLER = '/bitrix/sprod_integr_handler.php';

	/**
	 * Listen events of the portal
	 */
	public static function reg() {
		$sync_direction = Settings::get('direction');
		if ( ! $sync_direction || $sync_direction == 'full' || $sync_direction == 'ctos') {
			try {
				Rest::execute('event.bind', [
					'event'   => 'onCrmDealUpdate',
					'handler' => self::getCrmHandlersLink(),
				]);
				Rest::execute('event.bind', [
					'event'   => 'onCrmDealAdd',
					'handler' => self::getCrmHandlersLink(),
				]);
			} catch (\Exception $e) {
			}
			OfflineEvents::eventsBind();
		}
	}

	/**
	 * Check portal listeners
	 */
	public static function check() {
		$is_exist = false;
		$list = Rest::execute('event.get');
		if (is_array($list) && ! empty($list)) {
			$e_add_exist = false;
			$e_upd_exist = false;
			foreach ($list as $event) {
				if ($event['event'] == 'ONCRMDEALUPDATE' && $event['handler'] == self::getCrmHandlersLink()) {
					$e_upd_exist = true;
				}
			}
			foreach ($list as $event) {
				if ($event['event'] == 'ONCRMDEALADD' && $event['handler'] == self::getCrmHandlersLink()) {
					$e_add_exist = true;
				}
			}
			if ($e_upd_exist && $e_add_exist) {
				$is_exist = true;
			}
		}

		return $is_exist;
	}

	/**
	 * Remove portal listeners
	 */
	public static function unreg() {
		try {
			Rest::execute('event.unbind', [
				'event'   => 'onCrmDealUpdate',
				'handler' => self::getCrmHandlersLink(),
			]);
			Rest::execute('event.unbind', [
				'event'   => 'onCrmDealAdd',
				'handler' => self::getCrmHandlersLink(),
			]);
		} catch (\Exception $e) {
		}
		OfflineEvents::eventsUnbind();
	}

	/**
	 * Get portal listeners
	 */
	public static function getList() {
		$list = [
			'add' => [],
			'update' => [],
		];
		$full_list = Rest::execute('event.get');
		if (is_array($full_list) && ! empty($full_list)) {
			foreach ($full_list as $event) {
				if ($event['event'] == 'ONCRMDEALUPDATE' && $event['handler'] == self::getCrmHandlersLink()) {
					$list['update'][] = $event;
				}
				if ($event['event'] == 'ONCRMDEALADD' && $event['handler'] == self::getCrmHandlersLink()) {
					$list['add'][] = $event;
				}
			}
		}

		return $list;
	}

	public static function getCrmHandlersLink() {
		$link = Integration::getServerAddr() . self::EVENTS_HANDLER;
		return $link;
	}

}
