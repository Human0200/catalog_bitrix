<?php

namespace SProduction\Integration;

class SystemNotify {

	/**
	 *	Add notify
	 */
	public static function addNotify($strMessage, $strTag, $bClose=true){
		$arParams = [
			'MODULE_ID' => Integration::MODULE_ID,
			'MESSAGE' => $strMessage,
			'TAG' => $strTag,
			'ENABLE_CLOSE' => $bClose ? 'Y' : 'N',
		];
		static::deleteNotify($strTag);
		return \CAdminNotify::add($arParams);
	}

	/**
	 *	Delete notify
	 */
	public static function deleteNotify($strTag){
		return \CAdminNotify::deleteByTag($strTag);
	}

	/**
	 *	Get notify list
	 */
	public static function getNotifyList(){
		$arResult = [];
		$arSort = [
			'ID' => 'ASC',
		];
		$arFilter = [
			'MODULE_ID' => Integration::MODULE_ID,
		];
		$resItems = \CAdminNotify::getList($arSort, $arFilter);
		while($arItem = $resItems->getNext()){
			$arResult[] = $arItem;
		}
		return $arResult;
	}
}