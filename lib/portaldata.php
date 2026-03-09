<?php
/**
 * Different info from store
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

class PortalData
{
    const MODULE_ID = 'sproduction.integration';

	public static function getStartDateTs() {
		$start_date_ts = false;
		$start_date = Settings::get("start_date");
		if ($start_date) {
			$start_date_ts = strtotime(date('d.m.Y 00:00:00', strtotime($start_date)));
		}

		return $start_date_ts;
	}

	/**
	 * CRM info for sync process
	 */
	public static function getDealInfo($profile, $deal_id = 0) {
		$info = [
			'deal'           => [],
			'fields'         => [],
			'stages'         => [],
			'contact'        => [],
			'company'        => [],
			'requisite'      => [],
			'bankdetail'     => [],
			'products'       => [],
			'product_fields' => [],
			'assigned_user'  => [],
		];
		$request = [];
		if ($deal_id) {
			$request['deal'] = [
				'method' => 'crm.deal.get',
				'params' => ['id' => $deal_id]
			];
			$request['contact'] = [
				'method' => 'crm.contact.get',
				'params' => [
					'id' => '$result[deal][CONTACT_ID]',
				]
			];
			$request['company'] = [
				'method' => 'crm.company.get',
				'params' => [
					'id' => '$result[deal][COMPANY_ID]',
				]
			];
			$request['requisite'] = [
				'method' => 'crm.requisite.list',
				'params' => [
					'filter' => [
						'ENTITY_ID' => '$result[deal][COMPANY_ID]',
					],
				],
			];
			$request['bankdetail'] = [
				'method' => 'crm.requisite.bankdetail.list',
				'params' => [
					'filter' => [
						'ENTITY_ID' => '$result[requisite][0][ID]',
					],
				],
			];
			$request['assigned_user'] = [
				'method' => 'user.get',
				'params' => [
					'id' => '$result[deal][ASSIGNED_BY_ID]',
				]
			];
			$request['products'] = [
				'method' => 'crm.deal.productrows.get',
				'params' => [
					'id' => $deal_id,
				]
			];
		}
		$request['fields'] = [
			'method' => 'crm.deal.fields',
		];
		$dealcateg_id = (int) $profile['options']['deal_category'];
		if ( ! $dealcateg_id) {
			$request['stages'] = [
				'method' => 'crm.status.list',
				'params' => [
					'order'  => ['SORT' => 'ASC'],
					'filter' => [
						'ENTITY_ID' => 'DEAL_STAGE',
					]
				]
			];
		} else {
			$request['stages'] = [
				'method' => 'crm.dealcategory.stage.list',
				'params' => [
					'id' => $dealcateg_id,
				]
			];
		}
		$request['product_fields'] = [
			'method' => 'crm.product.fields',
		];
		$info = array_merge($info, Rest::batch($request));
		if ( ! empty($info['assigned_user'])) {
			$info['assigned_user'] = $info['assigned_user'][0];
		}
		// Correct empty arrays
		if (isset($info['deal']['ID'])) {
			if ( ! $info['deal']['CONTACT_ID']) {
				$info['contact'] = [];
			}
			if ( ! $info['deal']['COMPANY_ID']) {
				$info['company'] = [];
			}
			if (!empty($info['requisite']) && isset($info['requisite'][0])) {
				$info['requisite'] = $info['requisite'][0];
			}
			if (!empty($info['bankdetail']) && isset($info['bankdetail'][0])) {
				$info['bankdetail'] = $info['bankdetail'][0];
			}
		}

		return $info;
	}

	/**
	 * Search of deal
	 */
	public static function findDeal(array $order_data, $profile, $wo_categ=false, $try_num=3) {
		$deal_id = false;
		$filter = [
			'=' . Settings::getOrderIDField() => $order_data['ID'],
		];
		$source_id = Settings::get("source_id");
		if ($source_id) {
			$filter['=ORIGINATOR_ID'] = $source_id;
		}
		if ( ! $wo_categ) {
			$category_id = (int) $profile['options']['deal_category'];
			$filter['=CATEGORY_ID'] = $category_id;
		}
		\SProdIntegration::Log('(findDeal) order ' . $order_data['ID'] . ' filter ' . print_r($filter, true));
		$i = 0;
		while ( ! $deal_id && $i < $try_num) {
			if ($i > 0) {
				usleep(500000);
			}
			$res = Rest::execute('crm.deal.list', [
				'filter' => $filter,
				'start' => -1
			]);
			if ($res) {
				$deal_id = (int) $res[0]['ID'];
			}
			$i ++;
		}
		\SProdIntegration::Log('(findDeal) order ' . $order_data['ID'] . ' find deal ' . $deal_id);

		return $deal_id;
	}

	/**
	 * Deal data
	 */
	public static function getDeal($deals_ids) {
		$deals = [];
		if (is_array($deals_ids) && ! empty($deals_ids)) {
			$req_list = [];
			foreach ($deals_ids as $i => $deals_id) {
				$req_list[$i] = [
					'method' => 'crm.deal.get',
					'params' => [
						'id' => $deals_id,
					],
				];
			}
			$resp = Rest::batch($req_list);
			if ($resp) {
				foreach ($resp as $deal) {
					$deal['LINK'] = Settings::get("portal") . '/crm/deal/details/' . $deal['ID'] . '/';
					$deals[] = $deal;
				}
			}
		}

		return $deals;
	}


}
