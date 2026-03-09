<?php
/**
 * Event handlers of store
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

class StoreHandlers
{
    const MODULE_ID = 'sproduction.integration';

	/**
	 * Remove store listeners
	 */
	public static function unreg() {
		$eventManager = \Bitrix\Main\EventManager::getInstance();
		$eventManager->unRegisterEventHandler("sale", "OnSaleOrderSaved", Integration::MODULE_ID, '\SProduction\Integration\Integration', 'eventOnSaleOrderSaved');
		$eventManager->unRegisterEventHandler("crm", "OnAfterCrmDealUpdate", Integration::MODULE_ID, '\SProduction\Integration\Integration', 'eventOnAfterCrmDealUpdate');
	}

	/**
	 * Listen events of the store
	 */
	public static function reg() {
		$eventManager = \Bitrix\Main\EventManager::getInstance();
		$eventManager->registerEventHandler("sale", "OnSaleOrderSaved", Integration::MODULE_ID, '\SProduction\Integration\Integration', 'eventOnSaleOrderSaved');
		$eventManager->registerEventHandler("crm", "OnAfterCrmDealUpdate", Integration::MODULE_ID, '\SProduction\Integration\Integration', 'eventOnAfterCrmDealUpdate');
	}

	/**
	 * Check store handlers
	 */
	public static function check() {
		$res = false;
		$handlers = \Bitrix\Main\EventManager::getInstance()->findEventHandlers("sale", "OnSaleOrderSaved");
		if ( ! empty($handlers)) {
			foreach ($handlers as $handler) {
				if (
					$handler['TO_MODULE_ID'] == Integration::MODULE_ID &&
					$handler['TO_CLASS'] == '\SProduction\Integration\Integration' &&
					$handler['TO_METHOD'] == 'eventOnSaleOrderSaved'
				) {
					$res = true;
				}
			}
		}

		return $res;
	}

	/**
	 * Check store handlers
	 */
	public static function getList() {
		$list = [
			'OnSaleOrderSaved' => [],
			'OnAfterCrmDealUpdate' => [],
			'OnBeforeOrderUpdate' => [],
			'OnAfterOrderUpdate' => [],
			'OnBeforeCrmProductAdd' => [],
			'OnBeforeCrmProductUpdate' => [],
			'OnBeforeDealAdd' => [],
			'OnBeforeDealUpdate' => [],
			'OnAfterDealProcessed' => [],
		];
		$handlers = \Bitrix\Main\EventManager::getInstance()->findEventHandlers("sale", "OnSaleOrderSaved");
		if ( ! empty($handlers)) {
			foreach ($handlers as $handler) {
				$list['OnSaleOrderSaved'][] = $handler;
			}
		}
		$handlers = \Bitrix\Main\EventManager::getInstance()->findEventHandlers("crm", "OnAfterCrmDealUpdate");
		if ( ! empty($handlers)) {
			foreach ($handlers as $handler) {
				$list['OnAfterCrmDealUpdate'][] = $handler;
			}
		}
		$handlers = \Bitrix\Main\EventManager::getInstance()->findEventHandlers(self::MODULE_ID, "OnBeforeOrderUpdate");
		if ( ! empty($handlers)) {
			foreach ($handlers as $handler) {
				$list['OnBeforeOrderUpdate'][] = $handler;
			}
		}
		$handlers = \Bitrix\Main\EventManager::getInstance()->findEventHandlers(self::MODULE_ID, "OnAfterOrderUpdate");
		if ( ! empty($handlers)) {
			foreach ($handlers as $handler) {
				$list['OnAfterOrderUpdate'][] = $handler;
			}
		}
		$handlers = \Bitrix\Main\EventManager::getInstance()->findEventHandlers(self::MODULE_ID, "OnBeforeCrmProductAdd");
		if ( ! empty($handlers)) {
			foreach ($handlers as $handler) {
				$list['OnAfterOrderUpdate'][] = $handler;
			}
		}
		$handlers = \Bitrix\Main\EventManager::getInstance()->findEventHandlers(self::MODULE_ID, "OnBeforeCrmProductUpdate");
		if ( ! empty($handlers)) {
			foreach ($handlers as $handler) {
				$list['OnAfterOrderUpdate'][] = $handler;
			}
		}
		$handlers = \Bitrix\Main\EventManager::getInstance()->findEventHandlers(self::MODULE_ID, "OnBeforeDealAdd");
		if ( ! empty($handlers)) {
			foreach ($handlers as $handler) {
				$list['OnAfterOrderUpdate'][] = $handler;
			}
		}
		$handlers = \Bitrix\Main\EventManager::getInstance()->findEventHandlers(self::MODULE_ID, "OnBeforeDealUpdate");
		if ( ! empty($handlers)) {
			foreach ($handlers as $handler) {
				$list['OnAfterOrderUpdate'][] = $handler;
			}
		}
		$handlers = \Bitrix\Main\EventManager::getInstance()->findEventHandlers(self::MODULE_ID, "OnAfterDealProcessed");
		if ( ! empty($handlers)) {
			foreach ($handlers as $handler) {
				$list['OnAfterOrderUpdate'][] = $handler;
			}
		}
		return $list;
	}

}
