<?php
/**
 *    ProductsEdit
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

\Bitrix\Main\Loader::includeModule("catalog");

use Bitrix\Main,
	Bitrix\Main\Entity,
	Bitrix\Main\Type,
	Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);


class StoreProducts
{
	const LIST_FIELDS_DEF = [
		'ID',
		'IBLOCK_ID',
		'NAME',
		'CODE',
		'PICTURE',
		'DETAIL_PAGE_URL',
		'QUANTITY',
	];
	const LIST_FIELDS_SETTINGS_FIELD = 'store_prod_fields_sel';

	/**
	 * Get parent products
	 */
	public static function getParentProds($filter=[], $order=[], $select=[], $get_count=false, $limit=10, $page=1) {
		$req_filter = [
			'INCLUDE_SUBSECTIONS' => 'Y',
			'ACTIVE'              => 'Y',
		];
		if ($filter['iblock']) {
			$req_filter['IBLOCK_ID'] = $filter['iblock'];
		}
		else {
			$catalog_iblocks = ProfileInfo::getStoreIblockList();
			if (!empty($catalog_iblocks)) {
				foreach ($catalog_iblocks as $item) {
					$req_filter['IBLOCK_ID'][] = $item['id'];
				}
			}
		}
		if ($filter['name']) {
			$req_filter['NAME'] = '%' . $filter['name'] . '%';
		}
		if ($filter['section']) {
			$req_filter['SECTION_ID'] = $filter['section'];
		}
		if ($get_count) {
			return self::getCount($req_filter);
		}
		else {
			return self::getList($req_filter, $order, $select, $limit, $page, true);
		}
	}

	/**
	 * Get sku products
	 */
	public static function getSkuProds($iblock_id, $product_id, $get_count=false, array $fields=[], $limit=0, $page=1) {
		$catalog_info = \CCatalogSKU::GetInfoByProductIBlock($iblock_id);
		if (!$catalog_info) {
			return false;
		}
		$req_filter = [
			'IBLOCK_ID' => $catalog_info['IBLOCK_ID'],
			'PROPERTY_' . $catalog_info['SKU_PROPERTY_ID'] => $product_id,
			'ACTIVE' => 'Y',
		];
		if ($get_count) {
			return self::getCount($req_filter);
		}
		else {
			return self::getList($req_filter, [], $fields, $limit, $page);
		}
	}

	/**
	 * Get product data
	 */
	public static function getProduct($product_id) {
		if ($product = \Bitrix\Iblock\ElementTable::getList([
			'select' => ['*', 'DETAIL_PAGE_URL' => 'IBLOCK.DETAIL_PAGE_URL'],
			'filter' => ['ID' => $product_id],
		])->fetch()) {
			$product['DETAIL_PAGE_URL'] = Settings::get("site") . \CIBlock::ReplaceDetailUrl($product['DETAIL_PAGE_URL'], $product, false, 'E');
		}
		return $product;
	}

	/**
	 * Get count of products
	 */
	public static function getCount($filter=[]) {
		$count = \CIBlockElement::GetList([], $filter, []);
		return $count;
	}

	/**
	 * Get list of products
	 */
	public static function getList($filter=[], $order=[], $select=[], $limit=10, $page=1, $sku_count=false) {
		$store_prod_list = [];
		// Prepare params
		$site = Settings::get("site");
		if (empty($order)) {
			$order = ['NAME' => 'ASC', 'ID' => 'ASC'];
		}
		if (empty($select)) {
			$select = self::LIST_FIELDS_DEF;
		}
		else {
			if (!in_array('ID', $select)) {
				$select[] = 'ID';
			}
			if (!in_array('IBLOCK_ID', $select)) {
				$select[] = 'IBLOCK_ID';
			}
		}
		if (in_array('PICTURE', $select)) {
			if (!in_array('PREVIEW_PICTURE', $select)) {
				$select[] = 'PREVIEW_PICTURE';
			}
			if (!in_array('DETAIL_PICTURE', $select)) {
				$select[] = 'DETAIL_PICTURE';
			}
		}
		$page = (int) $page;
		$nav_params = [
			'nTopCount'       => false,
			'nPageSize'       => $limit,
			'iNumPage'        => $page,
			'checkOutOfRange' => true
		];
		$res = \CIBlockElement::GetList($order, $filter, false, $nav_params, $select);
		while ($fields = $res->GetNext()) {
			// Link on product page
			if ($fields['DETAIL_PAGE_URL']) {
				$link = $site . $fields['DETAIL_PAGE_URL'];
				$fields['PAGE_URL'] = '<a href="' . $link . '" target="_blank">' . Loc::getMessage("SP_CI_STOREPRODUCTS_GETLIST_SHOW") . '</a>';
			}
			// Preview
			$fields['PICTURE'] = '';
			if ($fields['PREVIEW_PICTURE'] || $fields['DETAIL_PICTURE']) {
				$image_resized = \CFile::ResizeImageGet(
					$fields['PREVIEW_PICTURE'] ? : $fields['DETAIL_PICTURE'],
					["width" => 100, "height" => 100],
					BX_RESIZE_IMAGE_PROPORTIONAL,
					true
				);
				$fields['PICTURE'] = '<img src="' . $image_resized["src"] . '" width="' . $image_resized["width"] . '" height="' . $image_resized["height"] . '" />';
			}
			// Properties
			foreach ($fields as $k => $value) {
				if (strpos($k, 'PROPERTY_') === 0) {
					$code = str_replace('_VALUE', '', $k);
					$fields[$code] = $value;
				}
			}
			// Count sku
			if ($sku_count) {
				$fields['SKU_COUNT'] = self::getSkuProds($fields['IBLOCK_ID'], $fields['ID'], true);
			}
			$store_prod_list[] = $fields;
		}
		return $store_prod_list;
	}


	/**
	 * Get list of iblock fields
	 */
	public static function getIblockFieldsList($iblock_id=false, array $selected=[]) {
		$result = [];
		// IBlock fields
		$list = [
			[
				'id' => 'ID',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_ID")
			],
			[
				'id' => 'IBLOCK_ID',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_IBLOCK_ID")
			],
			[
				'id' => 'SORT',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_SORT")
			],
			[
				'id' => 'NAME',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_NAME")
			],
			[
				'id' => 'CODE',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_CODE")
			],
			[
				'id' => 'ACTIVE',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_ACTIVE")
			],
			[
				'id' => 'DATE_ACTIVE_FROM',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_DATE_ACTIVE_FROM")
			],
			[
				'id' => 'DATE_ACTIVE_TO',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_DATE_ACTIVE_TO")
			],
			[
				'id' => 'TAGS',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_TAGS")
			],
			[
				'id' => 'PICTURE',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_PICTURE")
			],
			[
				'id' => 'PREVIEW_PICTURE',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_PREVIEW_PICTURE")
			],
			[
				'id' => 'DETAIL_PICTURE',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_DETAIL_PICTURE")
			],
			[
				'id' => 'PREVIEW_TEXT',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_PREVIEW_TEXT")
			],
			[
				'id' => 'DETAIL_TEXT',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_DETAIL_TEXT")
			],
			[
				'id' => 'DETAIL_PAGE_URL',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_DETAIL_PAGE_URL")
			],
			[
				'id' => 'DETAIL_PAGE_URL_HTML',
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_DETAIL_PAGE_URL_HTML")
			]
		];
		// IBlock properties
		if ($iblock_id) {
			$ob = \CIBlockProperty::GetList(["sort" => "asc", "name" => "asc"], ["ACTIVE" => "Y", "IBLOCK_ID" => $iblock_id]);
			while ($arProp = $ob->GetNext()) {
				if ($arProp['CODE']) {
					$list[] = [
						'id'   => 'PROPERTY_' . $arProp['CODE'],
						'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_PROP", ['#NAME#' => trim($arProp['NAME']), '#ID#' => $arProp['ID']]),
					];
				}
			}
		}
		// Catalog prices
		$list[] = [
			'id' => 'QUANTITY',
			'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_QUANTITY"),
		];
		$res = \Bitrix\Catalog\GroupTable::getList([
			'filter' => [],
			'order' => ['ID' => 'asc'],
		]);
		while ($item = $res->fetch()) {
			$list[] = [
				'id' => 'PRICE_'.$item['ID'],
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_PRICE", ['#NAME#' => trim($item['NAME']), '#ID#' => $item['ID']])
			];
			$list[] = [
				'id' => 'CURRENCY_'.$item['ID'],
				'name' => GetMessage("SP_CI_STOREPRODUCTS_FIELD_CURRENCY", ['#NAME#' => trim($item['NAME']), '#ID#' => $item['ID']])
			];
		}
		if (empty($selected)) {
			$result = $list;
		}
		else {
			foreach ($list as $item) {
				if (in_array($item['id'], $selected)) {
					$result[] = $item;
				}
			}
		}
		return $result;
	}

	/**
	 * Get list of displayed fields
	 */
	public static function getIblockFieldsSelected($iblock_id=false) {
		$iblock_id = (int)$iblock_id;
		$iblocks_fields = Settings::get(self::LIST_FIELDS_SETTINGS_FIELD, true);
		if (is_array($iblocks_fields) && isset($iblocks_fields[$iblock_id])) {
			$list = $iblocks_fields[$iblock_id];
		}
		else {
			$list = self::LIST_FIELDS_DEF;
		}
		return $list;
	}

	/**
	 * Change list of displayed fields
	 */
	public static function setIblockFieldsSelected($iblock_id, array $fields) {
		$iblock_id = (int)$iblock_id;
		$iblocks_fields = Settings::get(self::LIST_FIELDS_SETTINGS_FIELD, true);
		if (!is_array($iblocks_fields)) {
			$iblocks_fields = [];
		}
		$iblocks_fields[$iblock_id] = $fields;
		Settings::save(self::LIST_FIELDS_SETTINGS_FIELD, $iblocks_fields, true);
		return true;
	}

	/**
	 * Store products fields
	 */
	public static function getStoreFields($iblock_id) {
		$list = [];
		if ( ! $iblock_id) {
			return;
		}
		// IBlock fields
		$list['main'] = [
			'title' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_OSNOVNYE_PARAMETRY"),
		];
		$list['main']['items'] = [
			[
				'id'   => 'SORT',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_INDEKS_SORTIROVKI")
			],
			[
				'id'   => 'NAME',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_IMA_ELEMENTA")
			],
			[
				'id'   => 'CODE',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_KOD_ELEMENTA")
			],
			[
				'id'   => 'XML_ID',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_XML_ID")
			],
			[
				'id'   => 'ACTIVE',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_ACTIVE")
			],
			[
				'id'   => 'DATE_ACTIVE_FROM',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_NACALO_AKTIVNOSTI")
			],
			[
				'id'   => 'DATE_ACTIVE_TO',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_OKONCANIE_AKTIVNOSTI")
			],
			[
				'id'   => 'TAGS',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_TEGI")
			],
			[
				'id'   => 'PREVIEW_PICTURE',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_IZOBRAJENIE_DLA_ANON")
			],
			[
				'id'   => 'DETAIL_PICTURE',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_DETALQNOE_IZOBRAJENI")
			],
			[
				'id'   => 'PREVIEW_TEXT_TYPE',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_TIP_OPISANIA_DLA_ANO")
			],
			[
				'id'   => 'PREVIEW_TEXT',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_OPISANIE_DLA_ANONSA")
			],
			[
				'id'   => 'DETAIL_TEXT_TYPE',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_TIP_DETALQNOGO_OPISA")
			],
			[
				'id'   => 'DETAIL_TEXT',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_DETALQNOE_OPISANIE")
			],
			[
				'id'   => 'DETAIL_PAGE_URL',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_DETAIL_PAGE_URL")
			],
			[
				'id'   => 'DETAIL_PAGE_URL_HTML',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_DETAIL_PAGE_URL_HTML")
			],
		];
		// IBlock properties
		$list['props'] = [
			'title' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_SVOYSTVA"),
		];
		if ($iblock_id) {
			$ob = \CIBlockProperty::GetList(["sort" => "asc", "name" => "asc"], ["ACTIVE"    => "Y",
			                                                                     "IBLOCK_ID" => $iblock_id
			]);
			while ($arProp = $ob->GetNext()) {
				$list['props']['items']['PROP_' . $arProp['ID']] = [
					'id'   => 'PROP_' . $arProp['ID'],
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_SVOYSTVO") . $arProp['NAME'] . '"',
				];
			}
		}
		// Catalog prices
		$list['prices'] = [
			'title' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_PRICES"),
		];
		$res = \Bitrix\Catalog\GroupTable::getList([
			'filter' => [],
			'order'  => ['ID' => 'asc'],
		]);
		while ($item = $res->fetch()) {
			$list['prices']['items']['PRICE_' . $item['ID']] = [
				'id'   => 'PRICE_' . $item['ID'],
				'name' => $item['NAME'] . ', ID ' . $item['ID']
			];
		}
		// Catalog data
		$list['catalog'] = [
			'title' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_CATALOG"),
		];
		$list['catalog']['items'] = [
			[
				'id'   => 'CATALOG_QUANTITY',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_QUANTITY")
			],
			[
				'id'   => 'CATALOG_WEIGHT',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_WEIGHT")
			],
			[
				'id'   => 'CATALOG_WIDTH',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_WIDTH")
			],
			[
				'id'   => 'CATALOG_LENGTH',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_LENGTH")
			],
			[
				'id'   => 'CATALOG_HEIGHT',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_HEIGHT")
			],
			[
				'id'   => 'CATALOG_MEASURE',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_MEASURE")
			],
			[
				'id'   => 'CATALOG_VAT_ID',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_VAT_ID")
			],
			[
				'id'   => 'CATALOG_VAT_INCLUDED',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_VAT_INCLUDED")
			],
		];
		// Meta fields
		$list['meta'] = [
			'title' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_META"),
		];
		$list['meta']['items'] = [
			[
				'id'   => 'SEO_ELEMENT_META_TITLE',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_ELEMENT_META_TITLE")
			],
			[
				'id'   => 'SEO_ELEMENT_META_KEYWORDS',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_ELEMENT_META_KEYWORDS")
			],
			[
				'id'   => 'SEO_ELEMENT_META_DESCRIPTION',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_ELEMENT_META_DESCRIPTION")
			],
			[
				'id'   => 'SEO_ELEMENT_PAGE_TITLE',
				'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_ELEMENT_PAGE_TITLE")
			],
		];
		// PARENT IBLOCK DATA
		$catalog_iblocks = \Bitrix\Catalog\CatalogIblockTable::getList([
			'filter' => ['IBLOCK_ID' => $iblock_id]
		])->fetch();
		$parent_iblock_id = $catalog_iblocks['PRODUCT_IBLOCK_ID'];
		if ($parent_iblock_id) {
			// IBlock fields
			$list['parent_main'] = [
				'title' => GetMessage("SP_CI_CRMPRODUCTS_STORE_PARENT_FIELDS_OSNOVNYE_PARAMETRY"),
			];
			$list['parent_main']['items'] = [
				[
					'id'   => 'PARENT_SORT',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_INDEKS_SORTIROVKI")
				],
				[
					'id'   => 'PARENT_NAME',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_IMA_ELEMENTA")
				],
				[
					'id'   => 'PARENT_CODE',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_KOD_ELEMENTA")
				],
				[
					'id'   => 'PARENT_ACTIVE',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_ACTIVE")
				],
				[
					'id'   => 'PARENT_DATE_ACTIVE_FROM',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_NACALO_AKTIVNOSTI")
				],
				[
					'id'   => 'PARENT_DATE_ACTIVE_TO',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_OKONCANIE_AKTIVNOSTI")
				],
				[
					'id'   => 'PARENT_TAGS',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_TEGI")
				],
				[
					'id'   => 'PARENT_PREVIEW_PICTURE',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_IZOBRAJENIE_DLA_ANON")
				],
				[
					'id'   => 'PARENT_DETAIL_PICTURE',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_DETALQNOE_IZOBRAJENI")
				],
				[
					'id'   => 'PARENT_PREVIEW_TEXT_TYPE',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_TIP_OPISANIA_DLA_ANO")
				],
				[
					'id'   => 'PARENT_PREVIEW_TEXT',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_OPISANIE_DLA_ANONSA")
				],
				[
					'id'   => 'PARENT_DETAIL_TEXT_TYPE',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_TIP_DETALQNOGO_OPISA")
				],
				[
					'id'   => 'PARENT_DETAIL_TEXT',
					'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_DETALQNOE_OPISANIE")
				],
			];
			// IBlock properties
			$list['parent_props'] = [
				'title' => GetMessage("SP_CI_CRMPRODUCTS_STORE_PARENT_FIELDS_SVOYSTVA"),
			];
			if ($parent_iblock_id) {
				$ob = \CIBlockProperty::GetList(["sort" => "asc", "name" => "asc"], ["ACTIVE"    => "Y",
				                                                                     "IBLOCK_ID" => $parent_iblock_id
				]);
				while ($arProp = $ob->GetNext()) {
					$list['parent_props']['items']['PARENT_PROP_' . $arProp['ID']] = [
						'id'   => 'PARENT_PROP_' . $arProp['ID'],
						'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_SVOYSTVO") . $arProp['NAME'] . '"',
					];
				}
			}
		}

		return $list;
	}

	/**
	 * Store products fields
	 */
	public static function getStoreFieldsForID($iblock_id, $excludes=[]) {
		$list = [];
		if ( ! $iblock_id) {
			return;
		}
		// IBlock fields
		$list['main'] = [
			'title' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_OSNOVNYE_PARAMETRY"),
		];
		$list['main']['items'] = [];
		$list['main']['items'][] = [
			'id'   => 'ID',
			'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_ID")
		];
		$list['main']['items'][] = [
			'id'   => 'NAME',
			'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_IMA_ELEMENTA")
		];
		$list['main']['items'][] = [
			'id'   => 'CODE',
			'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_KOD_ELEMENTA")
		];
		$list['main']['items'][] = [
			'id'   => 'XML_ID',
			'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_XML_ID")
		];
		// IBlock properties
		$list['props'] = [
			'title' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_SVOYSTVA"),
		];
		if ($iblock_id) {
			$ob = \CIBlockProperty::GetList(["sort" => "asc", "name" => "asc"], ["ACTIVE"    => "Y",
			                                                                     "IBLOCK_ID" => $iblock_id
			]);
			while ($prop = $ob->GetNext()) {
				if ($prop['MULTIPLE'] != 'Y' && ! in_array($prop['PROPERTY_TYPE'], ['F'])) {
					$list['props']['items']['PROPERTY_' . $prop['ID']] = [
						'id'   => 'PROPERTY_' . $prop['ID'],
						'name' => GetMessage("SP_CI_CRMPRODUCTS_STORE_FIELDS_SVOYSTVO") . $prop['NAME'] . '"',
					];
				}
			}
		}

		return $list;
	}

	public static function getHLValue($hl_table, $value_code) {
		$hl_value = false;
		$hl_block = \Bitrix\Highloadblock\HighloadBlockTable::getList(
			array(
				"filter" => array(
					'TABLE_NAME' => $hl_table
				)
			)
		)->fetch();
		if (isset($hl_block['ID'])) {
			$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hl_block);
			$entity_data_class = $entity->getDataClass();
			$res = $entity_data_class::getList(['filter' => ['UF_XML_ID' => $value_code]]);
			if ($item = $res->fetch()) {
				$hl_value = $item['UF_NAME'];
			}
		}

		return $hl_value;
	}

	/**
	 * Params for search products in the CRM products DB
	 */
	public static function getSearchFields() {
		$iblocks = ProfileInfo::getStoreIblockList(true);
		$comp_table = [];
		$saved_table = Settings::get('products_search_store_fields', true);
		if ($saved_table) {
			foreach ($iblocks as $iblock) {
				$comp_table[$iblock['id']] = isset($saved_table[$iblock['id']]) ? $saved_table[$iblock['id']] : '';
			}
		} else {
			foreach ($iblocks as $iblock) {
				$comp_table[$iblock['id']] = '';
			}
			$products_iblock = (int) Settings::get('products_iblock');
			$field = Settings::get('products_search_store_field');
			if ($products_iblock && $field) {
				$comp_table[$products_iblock] = $field;
			}
		}
		return $comp_table;
	}

	public static function setSearchFields($iblock_id, $field) {
		$comp_table = self::getSearchFields();
		$comp_table[$iblock_id] = $field;
		Settings::save('products_search_store_fields', $comp_table, true);
	}

	/**
	 * Get search IDs for searching CRM products for store products
	 */
	public static function getCrmSearchIDs($store_prod_ids) {
		$store_prod_cmpr = [];
		// Get product id for search
		$store_fields = StoreProducts::getSearchFields();
		foreach ($store_prod_ids as $prod_id) {
			// Get iblock of product
			$res = \CIBlockElement::GetList(["SORT" => "ASC"], ["ID" => $prod_id], false, false, ['IBLOCK_ID']);
			if ($ob = $res->GetNextElement()) {
				$ib_product = $ob->GetFields();
				$product_iblock = $ib_product['IBLOCK_ID'];
				$search_store_field = $store_fields[$product_iblock];
				if ($search_store_field) {
					// Get value of id field
					$res = \CIBlockElement::GetList(["SORT" => "ASC"], ["ID" => $prod_id], false, false, [$search_store_field]);
					if ($ob = $res->GetNextElement()) {
						$ib_product = $ob->GetFields();
						if (strpos($search_store_field, 'PROPERTY_') !== false) {
							$value = $ib_product[$search_store_field . '_VALUE'];
						} else {
							$value = $ib_product[$search_store_field];
						}
						if ($value) {
							$store_prod_cmpr[$prod_id] = $value;
						}
					}
				}
			}
		}

		return $store_prod_cmpr;
	}

	/**
	 * CRM product IDs by CRM IDs
	 */
	public static function getIDsByCrmIDs($crm_prod_ids) {
		$prods_crm_store_ids = [];
		$crm_prods_store_search_ids = CrmProducts::getStoreSearchIDs($crm_prod_ids);
		$store_ids = self::find(array_values($crm_prods_store_search_ids));
		foreach ($crm_prods_store_search_ids as $crm_prod_id => $store_search_id) {
			if (isset($store_ids[$store_search_id]) && $store_ids[$store_search_id]) {
				$prods_crm_store_ids[$crm_prod_id] = $store_ids[$store_search_id];
			}
		}
		return $prods_crm_store_ids;
	}

	/**
	 * Find product by id
	 */

	public static function find($crm_prod_ids) {
		$store_prod_res = [];
		$comp_table = self::getSearchFields();
		foreach ($comp_table as $iblock_id => $search_fld) {
			if (!$iblock_id || !$search_fld) {
				continue;
			}
			$filter = [
				'ACTIVE' => 'Y',
				'IBLOCK_ID' => $iblock_id,
				$search_fld => $crm_prod_ids,
			];
			$res = \CIBlockElement::GetList(["SORT" => "ASC"], $filter, false, false, ['ID', $search_fld]);
			while ($ob = $res->GetNextElement()) {
				$ib_product = $ob->GetFields();
				$field_id = isset($ib_product[$search_fld . '_VALUE']) ? ($search_fld . '_VALUE') : $search_fld;
				$store_prod_res[$ib_product[$field_id]] = $ib_product['ID'];
			}
		}
		return $store_prod_res;
	}

}
