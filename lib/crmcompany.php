<?php
/**
 * CrmCompany
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
	Bitrix\Main\Entity,
	Bitrix\Main\Type,
	Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);


/**
 * Class CrmCompany
 *
 * @package SProduction\Integration
 **/

class CrmCompany
{

	/**
	 * Create new company for deal
	 */
	public static function runSync(array $order_data, $profile, $contact_id=false) {
		// Get contacts data
		$comp_fields = self::getDealCompanyDataByProfile($order_data, $profile);
		$comp_fields = Utilities::convEncForDeal($comp_fields);
		\SProdIntegration::Log('(syncOrderToDealCompany) order ' . $order_data['ID'] . ' comp_fields ' . print_r($comp_fields, true));
		$filter = [
			'inn'     => $comp_fields['requisite']['RQ_INN'],
			'iin'     => $comp_fields['requisite']['RQ_IIN'],
			'ogrn'    => $comp_fields['requisite']['RQ_OGRN'],
			'ogrnip'  => $comp_fields['requisite']['RQ_OGRNIP'],
			'account' => $comp_fields['bankdetail']['RQ_ACC_NUM'],
			'phone'   => $comp_fields['company']['PHONE'],
			'email'   => $comp_fields['company']['EMAIL'],
		];
		//\SProdIntegration::Log('(syncOrderToDealCompany) order '.$order_data['ID'].' search company by '.print_r($filter, true));
		$company_id = CrmCompany::find($filter);
		\SProdIntegration::Log('(syncOrderToDealCompany) order ' . $order_data['ID'] . ' company "' . $company_id . '"');
		try {
			if ( ! $company_id) {
				$company_id = CrmCompany::add($comp_fields, $contact_id);
			} else {
				CrmCompany::update($company_id, $comp_fields);
			}
		} catch (\Exception $e) {
			\SProdIntegration::Log('(syncOrderToDealCompany) can\'t sync of company');
			CrmNotif::sendMsg(Loc::getMessage("SP_CI_SYNC_COMPANY_ADD_ERROR"), 'ERRCMPADD', CrmNotif::TYPE_ERROR);
		}

		return $company_id;
	}

	/**
	 * Companies search
	 */
	public static function find($s_params) {
		$company_id = false;
		// Find by INN, OGRN, RS, email, phone
		$req_list = [];
		if ($s_params['inn']) {
			$req_list['requisites_inn'] = [
				'method' => 'crm.requisite.list',
				'params' => [
					'filter' => [
						'RQ_INN' => $s_params['inn'],
						'ENTITY_TYPE_ID' => 4,
					],
				],
			];
		}
		if ($s_params['iin']) {
			$req_list['requisites_iin'] = [
				'method' => 'crm.requisite.list',
				'params' => [
					'filter' => [
						'RQ_IIN' => $s_params['iin'],
						'ENTITY_TYPE_ID' => 4,
					],
				],
			];
		}
		if ($s_params['ogrn']) {
			$req_list['requisites_ogrn'] = [
				'method' => 'crm.requisite.list',
				'params' => [
					'filter' => [
						'RQ_OGRN' => $s_params['ogrn'],
						'ENTITY_TYPE_ID' => 4,
					],
				],
			];
		}
		elseif ($s_params['ogrnip']) {
			$req_list['requisites_ogrn'] = [
				'method' => 'crm.requisite.list',
				'params' => [
					'filter' => [
						'RQ_OGRNIP' => $s_params['ogrnip'],
						'ENTITY_TYPE_ID' => 4,
					],
				],
			];
		}
		if ($s_params['account']) {
			$req_list['bankdetail'] = [
				'method' => 'crm.requisite.bankdetail.list',
				'params' => [
					'filter' => [
						'RQ_ACC_NUM' => $s_params['account'],
					],
				],
			];
			$req_list['bankdetail_req'] = [
				'method' => 'crm.requisite.get',
				'params' => [
					'id' => '$result[bankdetail][0][ENTITY_ID]',
				],
			];
		}
		if ($s_params['phone']) {
			$req_list['companies_phone'] = [
				'method' => 'crm.company.list',
				'params' => [
					'filter' => [
						'PHONE' => $s_params['phone'],
					],
				],
			];
		}
		if ($s_params['email']) {
			$req_list['companies_email'] = [
				'method' => 'crm.company.list',
				'params' => [
					'filter' => [
						'EMAIL' => $s_params['email'],
					],
				],
			];
		}
		if (!empty($req_list)) {
			$res_list = Rest::batch($req_list);
			if ($res_list['requisites_inn'][0]) {
				$company_id = $res_list['requisites_inn'][0]['ENTITY_ID'];
				\SProdIntegration::Log('(CrmCompany::find) finded by inn');
			} elseif ($res_list['requisites_iin'][0]) {
				$company_id = $res_list['requisites_iin'][0]['ENTITY_ID'];
				\SProdIntegration::Log('(CrmCompany::find) finded by iin');
			} elseif ($res_list['requisites_ogrn'][0]) {
				$company_id = $res_list['requisites_ogrn'][0]['ENTITY_ID'];
				\SProdIntegration::Log('(CrmCompany::find) finded by ogrn');
			} elseif ($res_list['requisites_ogrnip'][0]) {
				$company_id = $res_list['requisites_ogrnip'][0]['ENTITY_ID'];
				\SProdIntegration::Log('(CrmCompany::find) finded by ogrnip');
			} elseif ($res_list['bankdetail'][0]) {
				$company_id = $res_list['bankdetail_req']['ENTITY_ID'];
				\SProdIntegration::Log('(CrmCompany::find) finded by bank account');
			} elseif ($res_list['companies_phone'][0]) {
				$company_id = $res_list['companies_phone'][0]['ID'];
				\SProdIntegration::Log('(CrmCompany::find) finded by phone');
			} elseif ($res_list['companies_email'][0]) {
				$company_id = $res_list['companies_email'][0]['ID'];
				\SProdIntegration::Log('(CrmCompany::find) finded by email');
			}
		}
		return $company_id;
	}

	/**
	 * Get company info
	 */
	public static function get($id) {
		$result = false;
		if ($id) {
			$req_list = [];
			$req_list['company'] = [
				'method' => 'crm.company.get',
				'params' => [
					'id' => $id,
				],
			];
			$req_list['requisite'] = [
				'method' => 'crm.requisite.list',
				'params' => [
					'filter' => [
						'ENTITY_ID' => $id,
					],
				],
			];
			$req_list['bankdetail'] = [
				'method' => 'crm.requisite.bankdetail.list',
				'params' => [
					'filter' => [
						'ENTITY_ID' => '$result[requisite][0][ID]',
					],
				],
			];
			$req_list['address'] = [
				'method' => 'crm.address.list',
				'params' => [
					'filter' => [
						'ENTITY_ID' => '$result[requisite][0][ID]',
					],
				],
			];
			$res_list = Rest::batch($req_list);
			if ($res_list) {
				$result = $res_list;
			}
		}
		return $result;
	}

	/**
	 * Create new company
	 */
	public static function add($params, $contact_id=false) {
		$result = false;
		if (!$params['company']['NAME']) {
			return $result;
		}
		$fields = [
			'TITLE' => $params['company']['NAME'],
		];
		if ($params['company']['PHONE']) {
			$fields['PHONE'] = [['VALUE' => $params['company']['PHONE']]];
		}
		if ($params['company']['EMAIL']) {
			$fields['EMAIL'] = [['VALUE' => $params['company']['EMAIL']]];
		}
		$responsible_id = $params['assigned_user'];
		if ($responsible_id) {
			$fields['ASSIGNED_BY_ID'] = $responsible_id;
		}
		$resp = Rest::execute('crm.company.add', [
			'fields' => $fields,
		], false, true, false);
		if ($resp['error_description']) {
			\SProdIntegration::Log('(CrmCompany::add) company error '.$resp['error_description']);
		}
		else {
			$company_id = $resp['result'];
		}
		if ($company_id) {
			$result = $company_id;
			$fields = [
				'ENTITY_ID'    => $company_id,
				'ENTITY_TYPE_ID' => 4,
				'PRESET_ID' => $params['company']['PRESET_ID'],
				'NAME' => $params['company']['NAME'],
			];
			foreach ($params['requisite'] as $param => $value) {
				$fields[$param] = $value;
			}
			$resp = Rest::execute('crm.requisite.add', [
				'fields' => $fields,
			], false, true, false);
			if ($resp['error_description']) {
				\SProdIntegration::Log('(CrmCompany::add) requisite error '.$resp['error_description']);
			}
			else {
				$requisite_id = $resp['result'];
			}
			if ($requisite_id) {
				if (!empty($params['bankdetail'])) {
					$fields = [
						'ENTITY_ID' => $requisite_id,
						'NAME'      => Loc::getMessage('SP_CI_CRMCOMPANY_BANKDETAIL_NAME_DEF'),
						'CODE'      => 'SHOP_REQ',
					];
					foreach ($params['bankdetail'] as $param => $value) {
						$fields[$param] = $value;
					}
					$resp = Rest::execute('crm.requisite.bankdetail.add', [
						'fields' => $fields,
					], false, true, false);
					if ($resp['error_description']) {
						\SProdIntegration::Log('(CrmCompany::add) bankdetail error '.$resp['error_description']);
					}
				}
				if (!empty($params['address_fact'])) {
					$fields = [
						'ENTITY_ID'      => $requisite_id,
						'ENTITY_TYPE_ID' => 8,
						'TYPE_ID'        => 1,
					];
					foreach ($params['address_fact'] as $param => $value) {
						$fields[$param] = $value;
					}
					$resp = Rest::execute('crm.address.add', [
						'fields' => $fields,
					], false, true, false);
					if ($resp['error_description']) {
						\SProdIntegration::Log('(CrmCompany::add) address_fact error '.$resp['error_description']);
					}
				}
				if (!empty($params['address_jur'])) {
					$fields = [
						'ENTITY_ID'      => $requisite_id,
						'ENTITY_TYPE_ID' => 8,
						'TYPE_ID'        => 6,
					];
					foreach ($params['address_jur'] as $param => $value) {
						$fields[$param] = $value;
					}
					$resp = Rest::execute('crm.address.add', [
						'fields' => $fields,
					], false, true, false);
					if ($resp['error_description']) {
						\SProdIntegration::Log('(CrmCompany::add) address_jur error '.$resp['error_description']);
					}
				}
			}
			// Add contact
			if ($contact_id > 0) {
				Rest::execute('crm.company.contact.add', [
					'id'     => $company_id,
					'fields' => [
						'CONTACT_ID' => $contact_id,
						'IS_PRIMARY' => 'Y',
					]
				]);
			}
		}
		return $result;
	}

	/**
	 * Update company info
	 */
	public static function update($id, $params) {
		$result = false;
		if ($id && !empty($params['company'])) {
			foreach ($params['company'] as $param => $value) {
				$fields[$param] = $value;
			}
			if ($params['company']['PHONE']) {
				$fields['PHONE'] = [['VALUE' => $params['company']['PHONE']]];
			}
			if ($params['company']['EMAIL']) {
				$fields['EMAIL'] = [['VALUE' => $params['company']['EMAIL']]];
			}
			if (UpdateLock::isChanged($id, 'company_stoc', $fields, true)) {
				$result = Rest::execute('crm.company.update', [
					'id'     => $id,
					'fields' => $fields,
				]);
			}
		}
		return $result;
	}

	/**
	 * Get company data by profile
	 */
	public static function getDealCompanyDataByProfile(array $order_data, $profile) {
		$comp_fields = [];
		// Other data
		$comp_fields['assigned_user'] = (int) $profile['options']['deal_respons_def'];
		// Data from comparable table
		$person_type = $order_data['PERSON_TYPE_ID'];
		$comp_table = (array) $profile['contact']['company_comp_table'][$person_type];
		$res = \CUser::GetByID($order_data['USER_ID']);
		$user_fields = $res->Fetch();
		foreach ($comp_table as $section_code => $section) {
			foreach ($section as $deal_f_id => $sync_params) {
				$order_f_id = $sync_params['value'];
				if ($order_f_id) {
					$value = false;
					// User fields
					if (in_array($deal_f_id, ['PRESET_ID'])) {
						$value = $order_f_id;
					} elseif ( ! (int) $order_f_id) {
						$value = $user_fields[$order_f_id];
					} // Properties
					else {
						foreach ($order_data['PROPERTIES'] as $prop) {
							if ($prop['ID'] == $order_f_id) {
								if ($prop['TYPE'] == 'FILE') {
									if (isset($prop['VALUE'][0])) {
										$file = $prop['VALUE'][0];
										$value = Utilities::getFileFieldForCrm($_SERVER['DOCUMENT_ROOT'] . $file['SRC']);
									}
								} else {
									$value = $prop['VALUE'][0];
								}
							}
						}
					}
					if ($value) {
						$comp_fields[$section_code][$deal_f_id] = $value;
					} else {
						$comp_fields[$section_code][$deal_f_id] = '';
					}
				}
			}
		}

		return $comp_fields;
	}
}