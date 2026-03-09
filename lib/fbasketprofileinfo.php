<?php
namespace SProduction\Integration;

use Bitrix\Main\Localization\Loc,
	Bitrix\Main\Text\Encoding,
	SProduction\Integration\Rest;

/**
 * Class for FBasket profile info
 */
class FBasketProfileInfo
{
	/**
	 * Preload CRM data
	 */
	public static function preloadCrmData($profile)
	{
		return ProfileInfo::preloadCrmData($profile);
	}

	/**
	 * Get CRM users
	 */
	public static function getCrmUsers($profile)
	{
		return ProfileInfo::getCrmUsers($profile);
	}

	/**
	 * Get CRM directions
	 */
	public static function getCrmDirections($profile)
	{
		return ProfileInfo::getCrmDirections($profile);
	}

	/**
	 * Get CRM sources
	 */
	public static function getCrmSources()
	{
		return ProfileInfo::getCrmSources();
	}

	/**
	 * Get CRM stages
	 */
	public static function getCrmStages($profile)
	{
		return ProfileInfo::getCrmStages($profile);
	}

	/**
	 * Get CRM contact fields
	 */
	public static function getCrmContactFields($profile)
	{
		$result = ProfileInfo::getCrmContactFields($profile, false);

		// Set default values for basic CRM fields to match user fields by code
		$fieldMapping = [
			'NAME' => 'NAME',           // CRM NAME -> User NAME
			'LAST_NAME' => 'LAST_NAME', // CRM LAST_NAME -> User LAST_NAME
			'SECOND_NAME' => 'SECOND_NAME', // CRM SECOND_NAME -> User SECOND_NAME
			'EMAIL' => 'EMAIL',         // CRM EMAIL -> User EMAIL
			'PHONE' => 'PHONE',         // CRM PHONE -> User PHONE
		];

		foreach ($fieldMapping as $crmField => $userField) {
			if (isset($result[$crmField])) {
				$result[$crmField]['default'] = $userField;
			}
		}

		return $result;
	}

	/**
	 * Get CRM deal fields
	 */
	public static function getCrmDealFields($profile)
	{
		// Fields
		$result = [];
		if (ProfileInfo::getPreloadData('deal_fields')) {
			$list = ProfileInfo::getPreloadData('deal_fields');
		}
		else {
			$list = Rest::execute('crm.deal.fields');
		}
		if (!\SProdIntegration::isUtf()) {
			$list = Encoding::convertEncoding($list, "UTF-8", "Windows-1251");
		}
		if (!empty($list)) {
			$accept_types = [
				ProfileInfo::DEAL_FIELDS_TYPE_LIST, ProfileInfo::DEAL_FIELDS_TYPE_STRING, ProfileInfo::DEAL_FIELDS_TYPE_LINK,
				ProfileInfo::DEAL_FIELDS_TYPE_FLOAT, ProfileInfo::DEAL_FIELDS_TYPE_INTEGER, ProfileInfo::DEAL_FIELDS_TYPE_FILE,
				ProfileInfo::DEAL_FIELDS_TYPE_DATETIME, ProfileInfo::DEAL_FIELDS_TYPE_DATE,
			];
			$exclude_fields = ['ID', 'UTM_SOURCE', 'UTM_CAMPAIGN', 'UTM_CONTENT', 'UTM_TERM', 'UTM_MEDIUM',
				'LAST_ACTIVITY_TIME', 'FACE_ID', 'ORIGIN_VERSION', 'ORIGIN_ID', 'ORIGINATOR_ID'];
			foreach ($list as $f_code => $item) {
				if ((in_array($item['type'], $accept_types) || in_array($f_code, ['TITLE', 'OPPORTUNITY', 'CURRENCY_ID']))
					&& !in_array($f_code, $exclude_fields) && !$item['isReadOnly']) {
					// User fields only (UF_)
					if (strpos($f_code, 'UF_') === 0) {
						$result[$f_code] = [
							'name'      => $item['formLabel'],
							'direction' => ProfileInfo::SYNC_STOC,
							'default'   => '',
							'hint'      => Loc::getMessage("SP_CI_DEAL_" . $f_code . "_HINT"),
						];
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Get CRM deal string fields for forgotten basket
	 */
	public static function getCrmDealStringFields($profile)
	{
		if (ProfileInfo::getPreloadData('deal_fields')) {
			$list = ProfileInfo::getPreloadData('deal_fields');
		}
		else {
			$list = Rest::execute('crm.deal.fields');
		}
		if (!\SProdIntegration::isUtf()) {
			$list = Encoding::convertEncoding($list, "UTF-8", "Windows-1251");
		}

		$result = [];
		if (!empty($list)) {
			$exclude_fields = ['ID', 'UTM_SOURCE', 'UTM_CAMPAIGN', 'UTM_CONTENT', 'UTM_TERM', 'UTM_MEDIUM',
				'LAST_ACTIVITY_TIME', 'FACE_ID', 'ORIGIN_VERSION', 'ORIGINATOR_ID'];
			foreach ($list as $f_code => $item) {
				if ($item['type'] == ProfileInfo::DEAL_FIELDS_TYPE_STRING
					&& !in_array($f_code, $exclude_fields) && !$item['isReadOnly']) {
					$result[] = [
						'id' => $f_code,
						'name' => $item['isDynamic'] ? $item['formLabel'] : $item['title']
					];
				}
			}
		}
		return $result;
	}

	/**
	 * Get CRM contact search fields
	 */
	public static function getCrmContactSFields($profile)
	{
		return ProfileInfo::getCrmContactSFields($profile);
	}



	/**
	 * Get site basket fields
	 */
	public static function getSiteBasketFields($profile)
	{
		$result = [];

		// Group: "Basket Data"
		$result['basket'] = [
			'title' => Loc::getMessage("SP_CI_PROFILE_BASKET_G_BASKET"),
			'items' => [],
		];

		// ID - Basket ID (FUSER_ID + LAST_ORDER_ID)
		$result['basket']['items'][] = [
			'ID' => 'ID',
			'types' => [ProfileInfo::DEAL_FIELDS_TYPE_STRING],
		];

		// FUSER_ID - Basket user ID
		$result['basket']['items'][] = [
			'ID' => 'FUSER_ID',
			'types' => [ProfileInfo::DEAL_FIELDS_TYPE_INTEGER, ProfileInfo::DEAL_FIELDS_TYPE_FLOAT, ProfileInfo::DEAL_FIELDS_TYPE_STRING],
		];

		// SITE_ID - Site code
		$result['basket']['items'][] = [
			'ID' => 'SITE_ID',
			'types' => [ProfileInfo::DEAL_FIELDS_TYPE_STRING],
		];

		// DATE_INSERT - Basket initialization date
		$result['basket']['items'][] = [
			'ID' => 'DATE_INSERT',
			'types' => [ProfileInfo::DEAL_FIELDS_TYPE_DATE, ProfileInfo::DEAL_FIELDS_TYPE_DATETIME],
		];

		// DATE_UPDATE - Basket last update date
		$result['basket']['items'][] = [
			'ID' => 'DATE_UPDATE',
			'types' => [ProfileInfo::DEAL_FIELDS_TYPE_DATE, ProfileInfo::DEAL_FIELDS_TYPE_DATETIME],
		];

		// Group: "User Basic Data" (from contact data)
		$contact_fields = ProfileInfo::getSiteContactFields($profile);
		if (isset($contact_fields['user'])) {
			$result['user'] = $contact_fields['user'];
			// Convert user fields to the same format as basket fields
			if (isset($result['user']['items'])) {
				$converted_items = [];
				foreach ($result['user']['items'] as $field_id => $field_name) {
					$converted_items[$field_id] = $field_name;
				}
				$result['user']['items'] = $converted_items;
			}
		}

		// Group: "User Custom Fields" (from contact data)
		if (isset($contact_fields['uf'])) {
			$result['uf'] = $contact_fields['uf'];
			// Convert uf fields to the same format as basket fields
			if (isset($result['uf']['items'])) {
				$converted_items = [];
				foreach ($result['uf']['items'] as $field_id => $field_name) {
					$converted_items[$field_id] = $field_name;
				}
				$result['uf']['items'] = $converted_items;
			}
		}

		// Add names, hints and CRM values for basket fields
		if (isset($result['basket']['items'])) {
			foreach ($result['basket']['items'] as $k => $prop) {
				$result['basket']['items'][$k] = Loc::getMessage('SP_CI_' . $prop['ID']);
				// CRM values
				// $result['basket']['items'][$k]['values'] = ProfileInfo::getCrmFields($profile, $prop['types']);
			}
		}

		return $result;
	}

	/**
	 * Get site contact fields
	 */
	public static function getSiteContactFields($profile)
	{
		$all_fields = ProfileInfo::getSiteContactFields($profile);

		$result = [];
		if (isset($all_fields['user'])) {
			$result['user'] = $all_fields['user'];
		}
		if (isset($all_fields['uf'])) {
			$result['uf'] = $all_fields['uf'];
		}

		return $result;
	}
}