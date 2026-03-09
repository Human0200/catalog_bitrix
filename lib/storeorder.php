<?php
/**
 * Work with order data
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
	Bitrix\Sale,
	Bitrix\Sale\Order,
	Bitrix\Sale\Basket,
	Bitrix\Sale\Delivery,
	Bitrix\Sale\PaySystem,
	Bitrix\Currency\CurrencyManager;

Loc::loadMessages(__FILE__);

class StoreOrder
{
    const MODULE_ID = 'sproduction.integration';

	/**
	 * Create new order from deal
	 */
	public static function createOrder($deal, $deal_info, $profile) {
		\SProdIntegration::Log('(createOrder) start creating');
		$order_id = false;
		$deal_id = $deal['ID'];
		// Base data
		$site = \SProdIntegration::getSiteDef();
		$site_id = $profile['neworder']['site_code'] ?? $site['LID'];
		$pay_type = $profile['neworder']['buyer_type'];
		// Get buyer
		$user_id = StoreUser::syncContactToBuyer($deal, $profile);
		// Create order
		$order = false;
		if ($user_id && $pay_type) {
			$order = Sale\Order::create($site_id, $user_id);
		}
		if ($order) {
			// Basic order data
			$basic_changed = self::setCreateBasic($deal, [], $deal_info, $profile, $order);
			$order_data = StoreData::getOrderInfo($order);
			// Update order products
			$products_changed = false;
			if (Settings::get("products_sync_type") == 'find_new') {
				$products_changed = self::updateProducts($deal_info, $order_data, $profile, $order, true);
			}
			// Additional actions for products
			if ($products_changed) {
				self::setShipment($order, $profile);
			}
			// Update status
			$status_changed = self::updateStatus($deal, $order_data, $profile, $order);
			// Update properties
			$props_changed = self::updateProps($deal, $order_data, $deal_info, $profile, $order);
			// Update different params by fields
			$params_changed = self::updateParams($deal, $order_data, $deal_info, $profile, $order);
			// Update other data
			$other_changed = self::updateOther($deal, $order_data, $deal_info, $profile, $order);
			// Save changes
			\SProdIntegration::Log('(createOrder) new order changed fields [status:' . $status_changed . ', props:' . $props_changed . ', params:' . $params_changed . ', other:' . $other_changed . ', products:' . $products_changed . ']');
			if ($basic_changed || $status_changed || $props_changed || $params_changed || $other_changed || $products_changed) {
//			Log::getInstance(Controller::$MODULE_ID, 'orders')->add('(OrderSync::runSync) save order ' . $order_id, $profile['ID'], true);
				// Check if deal is created
				if (!SyncOrder::getOrderIdByDeal(PortalData::getDeal([$deal_id]))) {
					if (Settings::get('run_save_final_action') != 'disabled') {
						$order->doFinalAction(true);
					}
					try {
						// Save new order
						$result = $order->save();
						if ( ! $result->isSuccess()) {
							\SProdIntegration::Log('(createOrder) save order error: ' . print_r($result->getErrors(), true));
						}
					} catch (\Exception $e) {
						\SProdIntegration::Log('(createOrder) save order exception: ' . $e->getMessage() . ' [' . $e->getCode() . ']');
						CrmNotif::sendMsg(Loc::getMessage("SP_CI_STOREORDER_ADD_ERROR", [
							'#ERROR_MSG#'  => $e->getMessage(),
							'#ERROR_CODE#' => $e->getCode(),
						]), 'ERRORDADD', CrmNotif::TYPE_ERROR, $deal_id);
					}
					try {
						$order_id = $order->getId();
					} catch (\Exception $e) {
						\SProdIntegration::Log('(createOrder) order not created');
					}
					if ($order_id) {
						\SProdIntegration::Log('(createOrder) order ' . $order_id . ' created success');
						$order = Sale\Order::load($order_id);
						$order_data = StoreData::getOrderInfo($order);
						\SProdIntegration::Log('(createOrder) created order data ' . print_r($order_data, true));
						CrmNotif::sendMsg(Loc::getMessage("SP_CI_STOREORDER_ADD_SUCCESS", [
							'#ORDER_ID#' => $order_id,
						]), 'SCSORDADD', CrmNotif::TYPE_SUCCESS, $deal_id);
					}
				}
			}
		}

		return $order_id;
	}

	/**
	 * Update order properties
	 */
	public static function updateOrder($order_data, array $deal, array $deal_info, $profile) {
		if (is_array($order_data) && isset($order_data['ID'])) {
			$order_id = $order_data['ID'];
			$order = Sale\Order::load($order_id);
			// Update status
			$status_changed = self::updateStatus($deal, $order_data, $profile, $order);
			// Update properties
			$props_changed = self::updateProps($deal, $order_data, $deal_info, $profile, $order);
			// Update different params by fields
			$params_changed = self::updateParams($deal, $order_data, $deal_info, $profile, $order);
			// Update other data
			$other_changed = self::updateOther($deal, $order_data, $deal_info, $profile, $order);
			// Update order products
			$products_changed = false;
			if (Settings::getSyncMode() == Settings::SYNC_MODE_NORMAL) {
				$products_changed = self::updateProducts($deal_info, $order_data, $profile, $order);
			}
			\SProdIntegration::Log('(updateOrder) order ' . $order_id . ' changed fields [status:' . $status_changed . ', props:' . $props_changed . ', params:' . $params_changed . ', other:' . $other_changed . ', products:' . $products_changed . ']');
			// Save changes
			if ($status_changed || $props_changed || $params_changed || $other_changed || $products_changed) {
				\SProdIntegration::Log('(updateOrder) updating order ' . $order_id);
				if (Settings::get('run_save_final_action') != 'disabled') {
					$order->doFinalAction(true);
				}
				try {
					$result = $order->save();
				if ( ! $result->isSuccess()) {
					\SProdIntegration::Log('(updateOrder) save order error: ' . print_r($result->getErrors(), true));
				} else {
					\SProdIntegration::Log('(updateOrder) updated order ' . $order_id);
					// Reset cash data
					UpdateLock::save($order_id, 'order_stoc', []);
					// Note: Field hashes will be updated in SyncHashManager::updateHashesAfterSync
					// which is called after this method returns
					// Process the event handlers
					foreach (GetModuleEvents(Integration::MODULE_ID, "OnAfterOrderUpdate", true) as $event) {
						ExecuteModuleEventEx($event, [$order_id]);
					}
					foreach (GetModuleEvents(Integration::MODULE_ID, "OnAfterOrderUpdateExt", true) as $event) {
						ExecuteModuleEventEx($event, [$order_id, $deal]);
					}
				}
				} catch (\Exception $e) {
					\SProdIntegration::Log('(updateOrder) save order exception: ' . $e->getMessage() . ' [' . $e->getCode() . ']');
				}
			}
		}
	}

	/**
	 * Update order properties
	 */
	public static function updateProps(array $deal, array $order_data, array $deal_info, $profile, &$order) {
		$has_changes = false;
		$deal_f_info = $deal_info['fields'];
		$o_new_values = [];
		$contact = $deal_info['contact'];
		$person_type = $order_data['PERSON_TYPE_ID'];
		// Formation of a table of correspondence fields
		$comp_table = [];
		$tmp_table = (array) $profile['props']['comp_table'];
		foreach ($tmp_table as $o_prop_id => $sync_params) {
			$d_field_code = $sync_params['value'];
			if ($d_field_code && ($sync_params['direction'] == 'all' || $sync_params['direction'] == 'ctos')
				&& isset($order_data['PROPERTIES'][$o_prop_id]) && $order_data['PROPERTIES'][$o_prop_id]['PERSON_TYPE_ID'] == $person_type) {
				$comp_table[$d_field_code] = $o_prop_id;
			}
		}
		// Formation of a table of correspondence fields for contact
		$contact_comp_table = [];
		$tmp_table = (array) $profile['contact']['comp_table'][$person_type];
		foreach ($tmp_table as $d_field_code => $sync_params) {
			$o_prop_id = $sync_params['value'];
			if ($o_prop_id && is_numeric($o_prop_id)) {
				$contact_comp_table[$d_field_code] = $o_prop_id;
			}
		}
		// Formation of a table of correspondence fields for company
		$company_comp_table = [];
		foreach (['address_fact', 'address_jur', 'bankdetail', 'requisite', 'company'] as $ct_block) {
			$tmp_table = (array) $profile['contact']['company_comp_table'][$person_type][$ct_block];
			foreach ($tmp_table as $d_field_code => $sync_params) {
				$o_prop_id = $sync_params['value'];
				if ($o_prop_id && is_numeric($o_prop_id)) {
					$company_comp_table[$ct_block][$d_field_code] = $o_prop_id;
				}
			}
		}
		\SProdIntegration::Log('(updateProps) order_data ' . print_r($order_data, true));
//		\SProdIntegration::Log('(updateProps) contact ' . print_r($contact, true));
//		\SProdIntegration::Log('(updateProps) company ' . print_r($deal_info['company'], true));
//		\SProdIntegration::Log('(updateProps) requisite ' . print_r($deal_info['requisite'], true));
//		\SProdIntegration::Log('(updateProps) bankdetail ' . print_r($deal_info['bankdetail'], true));
		\SProdIntegration::Log('(updateProps) properties compare table ' . print_r($comp_table, true));
//		\SProdIntegration::Log('(updateProps) contact compare table ' . print_r($contact_comp_table, true));
//		\SProdIntegration::Log('(updateProps) company compare table ' . print_r($company_comp_table, true));
		// Formation of a fields data for saving
		foreach ($comp_table as $d_field_code => $o_prop_id) {
			if (!isset($order_data['PROPERTIES'][$o_prop_id])) {
				continue;
			}
			$prop = $order_data['PROPERTIES'][$o_prop_id];
			\SProdIntegration::Log('(updateProps) prop ' . print_r($prop, true));
			$new_value = [];
			if ( ! is_array($deal[$d_field_code])) {
				$deal[$d_field_code] = [$deal[$d_field_code]];
			}
			// Deal types
			foreach ($deal[$d_field_code] as $k => $deal_value) {
				if ($deal_f_info[$d_field_code]['type'] == 'money') {
					$arTmp = explode('|', $deal_value);
					$deal[$d_field_code][$k] = $arTmp[0];
				}
				if ($deal_f_info[$d_field_code]['type'] == 'boolean') {
					$deal[$d_field_code][$k] = $deal_value == 1 ? 'Y' : 'N';
				}
			}
			// Store types
			if ($prop['TYPE'] == 'LOCATION') {
				continue;
			}
			if ($prop['TYPE'] == 'ENUM') {
				foreach ($deal[$d_field_code] as $deal_value) {
					$deal_value = self::getDealFieldValue($d_field_code, $deal_value, $deal_f_info);
					foreach ($prop['OPTIONS'] as $prop_code => $prop_val) {
						if (trim($prop_val) == trim($deal_value)) {
							$new_value[] = $prop_code;
						}
					}
				}
			} elseif ($prop['TYPE'] == 'FILE') {
				if (UpdateLock::isChanged($deal['ID'] . '_' . $d_field_code, 'deal_files_ctos', $deal[$d_field_code], true)) {
					if ( ! $deal_f_info[$d_field_code]['isMultiple']) {
						$deal[$d_field_code] = [$deal[$d_field_code]];
					}
					foreach ($deal[$d_field_code] as $k => $deal_value) {
						if ($deal_value['downloadUrl']) {
							$app_info = Rest::getAppInfo();
							$file = $app_info['portal'] . $deal_value['downloadUrl'];
							$file_old = $_SERVER['DOCUMENT_ROOT'] . $prop['VALUE'][$k]['SRC'];
							$file_hash = md5(file_get_contents($file));
							$file_old_hash = md5(file_get_contents($file_old));
							\SProdIntegration::Log('(updateOrderProps) order ' . $order_data['ID'] . ' files hash ' . print_r([
									$file_hash,
									$file_old_hash
								], true));
							if ($file_hash != $file_old_hash) {
								$arFile = \CFile::MakeFileArray($file);
								$arFile['name'] = strtolower(base64_encode($deal_value['id']));
								$all_mimes = '{"png":["image\/png","image\/x-png"],"bmp":["image\/bmp","image\/x-bmp","image\/x-bitmap","image\/x-xbitmap","image\/x-win-bitmap","image\/x-windows-bmp","image\/ms-bmp","image\/x-ms-bmp","application\/bmp","application\/x-bmp","application\/x-win-bitmap"],"gif":["image\/gif"],"jpeg":["image\/jpeg","image\/pjpeg"],"xspf":["application\/xspf+xml"],"vlc":["application\/videolan"],"wmv":["video\/x-ms-wmv","video\/x-ms-asf"],"au":["audio\/x-au"],"ac3":["audio\/ac3"],"flac":["audio\/x-flac"],"ogg":["audio\/ogg","video\/ogg","application\/ogg"],"kmz":["application\/vnd.google-earth.kmz"],"kml":["application\/vnd.google-earth.kml+xml"],"rtx":["text\/richtext"],"rtf":["text\/rtf"],"jar":["application\/java-archive","application\/x-java-application","application\/x-jar"],"zip":["application\/x-zip","application\/zip","application\/x-zip-compressed","application\/s-compressed","multipart\/x-zip"],"7zip":["application\/x-compressed"],"xml":["application\/xml","text\/xml"],"svg":["image\/svg+xml"],"3g2":["video\/3gpp2"],"3gp":["video\/3gp","video\/3gpp"],"mp4":["video\/mp4"],"m4a":["audio\/x-m4a"],"f4v":["video\/x-f4v"],"flv":["video\/x-flv"],"webm":["video\/webm"],"aac":["audio\/x-acc"],"m4u":["application\/vnd.mpegurl"],"pdf":["application\/pdf","application\/octet-stream"],"pptx":["application\/vnd.openxmlformats-officedocument.presentationml.presentation"],"ppt":["application\/powerpoint","application\/vnd.ms-powerpoint","application\/vnd.ms-office","application\/msword"],"docx":["application\/vnd.openxmlformats-officedocument.wordprocessingml.document"],"xlsx":["application\/vnd.openxmlformats-officedocument.spreadsheetml.sheet","application\/vnd.ms-excel"],"xl":["application\/excel"],"xls":["application\/msexcel","application\/x-msexcel","application\/x-ms-excel","application\/x-excel","application\/x-dos_ms_excel","application\/xls","application\/x-xls"],"xsl":["text\/xsl"],"mpeg":["video\/mpeg"],"mov":["video\/quicktime"],"avi":["video\/x-msvideo","video\/msvideo","video\/avi","application\/x-troff-msvideo"],"movie":["video\/x-sgi-movie"],"log":["text\/x-log"],"txt":["text\/plain"],"css":["text\/css"],"html":["text\/html"],"wav":["audio\/x-wav","audio\/wave","audio\/wav"],"xhtml":["application\/xhtml+xml"],"tar":["application\/x-tar"],"tgz":["application\/x-gzip-compressed"],"psd":["application\/x-photoshop","image\/vnd.adobe.photoshop"],"exe":["application\/x-msdownload"],"js":["application\/x-javascript"],"mp3":["audio\/mpeg","audio\/mpg","audio\/mpeg3","audio\/mp3"],"rar":["application\/x-rar","application\/rar","application\/x-rar-compressed"],"gzip":["application\/x-gzip"],"hqx":["application\/mac-binhex40","application\/mac-binhex","application\/x-binhex40","application\/x-mac-binhex40"],"cpt":["application\/mac-compactpro"],"bin":["application\/macbinary","application\/mac-binary","application\/x-binary","application\/x-macbinary"],"oda":["application\/oda"],"ai":["application\/postscript"],"smil":["application\/smil"],"mif":["application\/vnd.mif"],"wbxml":["application\/wbxml"],"wmlc":["application\/wmlc"],"dcr":["application\/x-director"],"dvi":["application\/x-dvi"],"gtar":["application\/x-gtar"],"php":["application\/x-httpd-php","application\/php","application\/x-php","text\/php","text\/x-php","application\/x-httpd-php-source"],"swf":["application\/x-shockwave-flash"],"sit":["application\/x-stuffit"],"z":["application\/x-compress"],"mid":["audio\/midi"],"aif":["audio\/x-aiff","audio\/aiff"],"ram":["audio\/x-pn-realaudio"],"rpm":["audio\/x-pn-realaudio-plugin"],"ra":["audio\/x-realaudio"],"rv":["video\/vnd.rn-realvideo"],"jp2":["image\/jp2","video\/mj2","image\/jpx","image\/jpm"],"tiff":["image\/tiff"],"eml":["message\/rfc822"],"pem":["application\/x-x509-user-cert","application\/x-pem-file"],"p10":["application\/x-pkcs10","application\/pkcs10"],"p12":["application\/x-pkcs12"],"p7a":["application\/x-pkcs7-signature"],"p7c":["application\/pkcs7-mime","application\/x-pkcs7-mime"],"p7r":["application\/x-pkcs7-certreqresp"],"p7s":["application\/pkcs7-signature"],"crt":["application\/x-x509-ca-cert","application\/pkix-cert"],"crl":["application\/pkix-crl","application\/pkcs-crl"],"pgp":["application\/pgp"],"gpg":["application\/gpg-keys"],"rsa":["application\/x-pkcs7"],"ics":["text\/calendar"],"zsh":["text\/x-scriptzsh"],"cdr":["application\/cdr","application\/coreldraw","application\/x-cdr","application\/x-coreldraw","image\/cdr","image\/x-cdr","zz-application\/zz-winassoc-cdr"],"wma":["audio\/x-ms-wma"],"vcf":["text\/x-vcard"],"srt":["text\/srt"],"vtt":["text\/vtt"],"ico":["image\/x-icon","image\/x-ico","image\/vnd.microsoft.icon"],"csv":["text\/x-comma-separated-values","text\/comma-separated-values","application\/vnd.msexcel"],"json":["application\/json","text\/json"]}';
								$all_mimes = json_decode($all_mimes, true);
								foreach ($all_mimes as $ext => $arTypes) {
									if (array_search($arFile['type'], $arTypes) !== false) {
										$arFile['name'] .= '.' . $ext;
									}
								}
								$file_id = \CFile::SaveFile($arFile, "sale");
								if ($file_id) {
									$new_value[] = $file_id;
								}
							} else {
								$new_value[] = $prop['VALUE'][$k]['ID'];
							}
						}
					}
					// Convert current prop format
					$prop_values = [];
					foreach ($prop['VALUE'] as $value) {
						$prop_values[] = $value['ID'];
					}
					$prop['VALUE'] = $prop_values;
				} else {
					continue;
				}
			} elseif ($prop['TYPE'] == 'DATE') {
				$new_value = $deal[$d_field_code];
				if ($new_value[0]) {
					if ($prop['TIME'] == 'Y') {
						$new_value[0] = ConvertTimeStamp(strtotime($new_value[0]), "FULL", SITE_ID);
					} else {
						$new_value[0] = ConvertTimeStamp(strtotime($new_value[0]), "SHORT", SITE_ID);
					}
				}
				$new_value = Utilities::convEncForOrder($new_value);
			} else {
				$new_value = Utilities::convEncForOrder($deal[$d_field_code]);
			}
			// Has new value
			//\SProdIntegration::Log('(updateOrderProps) order ' . $order_data['ID'] . ' compare values ' . print_r([$prop['VALUE'], $new_value], true));
			if ( ! self::isEqual($prop['VALUE'], $new_value)) {
				// Check if field has changed using FieldUpdateLock
				if (FieldUpdateLock::isChanged($deal['ID'], 'deal', $d_field_code, $deal[$d_field_code])) {
					$o_new_values[$o_prop_id] = count($new_value) == 1 ? $new_value[0] : $new_value;
				}
			}
		}
		// Formation of a contact data for saving
		foreach ($contact_comp_table as $d_field_code => $o_prop_id) {
			// Only for empty props
			if (!isset($order_data['PROPERTIES'][$o_prop_id]) || !self::isEqual($order_data['PROPERTIES'][$o_prop_id]['VALUE'], []) ||
				array_search($o_prop_id, $comp_table)) {
				continue;
			}
			$prop = $order_data['PROPERTIES'][$o_prop_id];
			$new_value = [];
			if (!in_array($prop['TYPE'], ['LOCATION', 'ENUM', 'FILE', 'DATE']) && isset($contact[$d_field_code])) {
				$d_value = $contact[$d_field_code];
				if ($d_field_code == 'EMAIL' || $d_field_code == 'PHONE') {
					$d_value = $d_value[0]['VALUE'];
				}
				$new_value = Utilities::convEncForOrder($d_value);
			}
			$new_value = is_array($new_value) ? $new_value : [$new_value];
			if ( ! self::isEqual($prop['VALUE'], $new_value)) {
				$o_new_values[$o_prop_id] = count($new_value) == 1 ? $new_value[0] : $new_value;;
			}
		}
		// Formation of a company data for saving
		foreach ($company_comp_table as $ct_block_code => $ct_block) {
			foreach ($ct_block as $d_field_code => $o_prop_id) {
				if (!isset($order_data['PROPERTIES'][$o_prop_id]) || !self::isEqual($order_data['PROPERTIES'][$o_prop_id]['VALUE'], []) ||
					array_search($o_prop_id, $comp_table) || array_search($o_prop_id, $contact_comp_table)) {
					continue;
				}
				$prop = $order_data['PROPERTIES'][$o_prop_id];
				$new_value = [];
				$d_field_code = ($d_field_code == 'NAME') ? 'TITLE' : $d_field_code;
				if (!in_array($prop['TYPE'], ['LOCATION', 'ENUM', 'FILE', 'DATE']) && isset($deal_info[$ct_block_code][$d_field_code])) {
					$d_value = $deal_info[$ct_block_code][$d_field_code];
					if ($d_field_code == 'EMAIL' || $d_field_code == 'PHONE') {
						$d_value = $d_value[0]['VALUE'];
					}
					$new_value = Utilities::convEncForOrder($d_value);
				}
				$new_value = is_array($new_value) ? $new_value : [$new_value];
				if ( ! self::isEqual($prop['VALUE'], $new_value)) {
					$o_new_values[$o_prop_id] = $new_value;
				}
			}
		}
		// Save new values
		\SProdIntegration::Log('(updateOrderProps) order ' . $order_data['ID'] . ' new props ' . print_r($o_new_values, true));
		foreach ($o_new_values as $o_prop_id => $new_value) {
			$property_collection = $order->getPropertyCollection();
			$prop_value = $property_collection->getItemByOrderPropertyId($o_prop_id);
			$prop_value->setValue($new_value);
			$has_changes = true;
		}

		return $has_changes;
	}

	public static function getDealFieldValue($f_code, $f_value, $deal_f_info) {
		$result = false;
		if (isset($deal_f_info[$f_code]['items']) && $f_value) {
			foreach ($deal_f_info[$f_code]['items'] as $d_f_item) {
				if ($d_f_item['ID'] == $f_value) {
					$result = Utilities::convEncForOrder($d_f_item['VALUE']);
					break;
				}
			}
		} else {
			$result = Utilities::convEncForOrder($f_value);
		}

		return $result;
	}

	/**
	 * Values equal check
	 */
	public static function isEqual($order_value, $deal_value, $entity_id=false) {
		$res = false;
		if ($order_value == [false]) {
			$order_value = [];
		}
		if ($deal_value == [false]) {
			$deal_value = [];
		}
		if ( ! is_array($order_value) && ! is_array($deal_value)) {
			if ($order_value == $deal_value) {
				$res = true;
			}
		} elseif (is_array($order_value) && is_array($deal_value)) {
			if (count($order_value) == count($deal_value)) {
				$res = true;
				// For files compare with last local version
				if (isset($order_value[0]['fileId']) && $order_value[0]['fileId']) {
					if ($entity_id) {
						if (UpdateLock::isChanged($entity_id, 'file_stoc', $order_value, true)) {
							$res = false;
						}
					}
				} // Other types
				else {
					foreach ($order_value as $k => $value) {
						if ($value != $deal_value[$k]) {
							$res = false;
						}
					}
					foreach ($deal_value as $k => $value) {
						if ($value != $order_value[$k]) {
							$res = false;
						}
					}
				}
			}
		}

		return $res;
	}

	public static function updateByNewDeal($deal, $order_data, $deal_info, $profile) {
		// Check order data
		$order_id = $order_data['ID'];
		if ($order_id) {
			$order = Sale\Order::load($order_id);
		}
		// Update properties
		$props_changed = self::updateProps($deal, $order_data, $deal_info, $profile, $order);
		// Save changes
		if ($props_changed) {
			\SProdIntegration::Log('(updateOrderByNewDeal) updated order ' . $order_id);
			$order->doFinalAction(true);
			$order->save();
		}
	}


	/**
	 * Update order status
	 */
	public static function updateStatus(array $deal, array $order_data, $profile, &$order) {
		$has_changes = false;
		// Formation of a table of correspondence fields
		$status_table = [];
		$tmp_table = (array) $profile['statuses']['comp_table'];
		foreach ($tmp_table as $o_status => $sync_params) {
			$d_stages = $sync_params['stages'];
			$d_stages = array_diff($d_stages, ['']);
			if ( ! empty($d_stages) && $sync_params['direction'] == 'all' || $sync_params['direction'] == 'ctos') {
				foreach ($d_stages as $d_stage) {
					$status_table[$d_stage][] = $o_status;
				}
			}
		}
		$cancel_table = (array) $profile['statuses']['cancel_stages'];
		// Formation of a data for saving
		$new_d_stage = $deal['STAGE_ID'];
		$new_o_statuses = $status_table[$new_d_stage];
		$cur_o_status = $order_data['STATUS_ID'];
		if ($new_d_stage && ! empty($new_o_statuses) && ! in_array($cur_o_status, $new_o_statuses)) {
			// Check if STAGE_ID field has changed using FieldUpdateLock
			if (FieldUpdateLock::isChanged($deal['ID'], 'deal', 'STAGE_ID', $new_d_stage)) {
				$order->setField('STATUS_ID', $new_o_statuses[0]);
				$has_changes = true;
				\SProdIntegration::Log('(updateOrderProps) order ' . $order_data['ID'] . ' new status: ' . $new_o_statuses[0]);
			}
		}
		if ($new_d_stage && ! empty($cancel_table)) {
			if (in_array($new_d_stage, $cancel_table) && $order_data['IS_CANCELED'] != 'Y') {
				// Check if STAGE_ID field has changed using FieldUpdateLock
				if (FieldUpdateLock::isChanged($deal['ID'], 'deal', 'STAGE_ID', $new_d_stage)) {
					$order->setField('CANCELED', 'Y');
					\SProdIntegration::Log('(updateOrderProps) order ' . $order_data['ID'] . ' canceled: Y');
					if (Settings::get('cancel_pays_by_cancel_order')) {
						$payments = $order->getPaymentCollection();
						foreach ($payments as $payment) {
							$payment->setPaid('N');
							$payment->save();
						}
					}
					$has_changes = true;
				}
			} elseif ( ! in_array($new_d_stage, $cancel_table) && $order_data['IS_CANCELED'] == 'Y') {
				// Check if STAGE_ID field has changed using FieldUpdateLock
				if (FieldUpdateLock::isChanged($deal['ID'], 'deal', 'STAGE_ID', $new_d_stage)) {
					\SProdIntegration::Log('(updateOrderProps) order ' . $order_data['ID'] . ' canceled: N');
					$order->setField('CANCELED', 'N');
					$has_changes = true;
				}
			}
		}

		return $has_changes;
	}

	/**
	 * Update other order properties by deal fields
	 */
	public static function updateParams(array $deal, array $order_data, array $deal_info, $profile, &$order) {
		$has_changes = false;
		$deal_f_info = $deal_info['fields'];
		// Formation of a table of correspondence fields
		$comp_table = [];
		$tmp_table = (array) $profile['other']['comp_table'];
		foreach ($tmp_table as $o_field_type => $sync_params) {
			$d_field_code = $sync_params['value'];
			if ($d_field_code && ($sync_params['direction'] == 'all' || $sync_params['direction'] == 'ctos')) {
				$comp_table[$d_field_code] = $o_field_type;
			}
		}
		// Formation of a data for saving
		foreach ($comp_table as $d_field_code => $o_field_type) {
			$f_has_changes = false;
			$new_value = [];
			if ( ! is_array($deal[$d_field_code])) {
				$deal[$d_field_code] = [$deal[$d_field_code]];
			}
			// Deal types
			foreach ($deal[$d_field_code] as $k => $deal_value) {
				if ($deal_f_info[$d_field_code]['type'] == 'money') {
					$arTmp = explode('|', $deal_value);
					$deal[$d_field_code][$k] = $arTmp[0];
				}
				if ($deal_f_info[$d_field_code]['type'] == 'date' && $deal_value) {
					$deal[$d_field_code][$k] = ConvertTimeStamp(strtotime($deal_value), "FULL", SITE_ID);
				}
				if ($deal_f_info[$d_field_code]['type'] == 'boolean') {
					$deal[$d_field_code][$k] = $deal_value ? 'Y' : 'N';
				}
			}
			// Prepare value
			$new_value = $deal[$d_field_code];
			$new_value = $new_value[0];
			$new_value = self::getDealFieldValue($d_field_code, $new_value, $deal_f_info);
			// Store types
			switch ($o_field_type) {
				case 'DELIV_TYPE':
					// Find new delivery system ID
					$res = \Bitrix\Sale\Delivery\Services\Table::getList([
						'filter' => ['NAME' => $new_value, 'ACTIVE' => 'Y']
					]);
					if ($row = $res->fetch()) {
						$new_deliv_id = $row['ID'];
					if ($new_deliv_id && $new_deliv_id != $order_data['DELIVERY_TYPE_ID']) {
						// Check if field has changed using FieldUpdateLock
						if (FieldUpdateLock::isChanged($deal['ID'], 'deal', $d_field_code, $deal[$d_field_code])) {
							// Change delivery system
							$shipment = self::getShipment($order);
							if (!$shipment) {
								$shipment_collection = $order->getShipmentCollection();
								$shipment = $shipment_collection->createItem(Delivery\Services\Manager::getObjectById($new_deliv_id));
							}
							if (is_object($shipment)) {
								$shipment->setFields([
									'DELIVERY_ID' => $new_deliv_id,
								]);
								$new_deliv_cost = $shipment->calculateDelivery()->getDeliveryPrice();
								$shipment->setFields([
									'CUSTOM_PRICE_DELIVERY' => 'Y',
									'PRICE_DELIVERY'        => $new_deliv_cost,
								]);
								$has_changes = true;
								$f_has_changes = true;
								CrmNotif::sendMsg(Loc::getMessage("SP_CI_STOREORDER_NEW_DELIV_TYPE", [
									'#NEW_TYPE#' => $new_value,
									'#NEW_COST#' => $new_deliv_cost,
								]), 'SCSDELIVCHANGE', CrmNotif::TYPE_SUCCESS, $deal['ID']);
							}
						}
					}
					}
					break;
				case 'DELIVERY_PRICE':
					if (floatval($new_value) != floatval($order_data['DELIVERY_PRICE'])) {
						// Check if field has changed using FieldUpdateLock
						if (FieldUpdateLock::isChanged($deal['ID'], 'deal', $d_field_code, $deal[$d_field_code])) {
							$shipment = self::getShipment($order);
							if ($shipment) {
								$shipment->setFields([
									'PRICE_DELIVERY'        => $new_value,
									'CUSTOM_PRICE_DELIVERY' => 'Y',
								]);
								$has_changes = true;
								$f_has_changes = true;
							}
						}
					}
					break;
				case 'DELIV_TRACKNUM':
					if ($new_value != $order_data['TRACKING_NUMBER']) {
						// Check if field has changed using FieldUpdateLock
						if (FieldUpdateLock::isChanged($deal['ID'], 'deal', $d_field_code, $deal[$d_field_code])) {
							$shipment = self::getShipment($order);
							if ($shipment) {
								$shipment->setFields([
									'TRACKING_NUMBER' => $new_value
								]);
								$has_changes = true;
								$f_has_changes = true;
							}
						}
					}
					break;
				case 'DELIVERY_STATUS_NAME':
					$res = \Bitrix\Sale\Internals\StatusLangTable::getList(array(
						'order'  => ['STATUS.SORT' => 'ASC'],
						'filter' => ['STATUS.TYPE' => 'D', 'LID' => LANGUAGE_ID],
						'select' => ['STATUS_ID', 'NAME'],
					));
					while ($item = $res->fetch()) {
						$status_list[$item['NAME']] = $item['STATUS_ID'];
					}
					$new_value = trim($new_value);
					$cur_value = $order_data[$o_field_type];
					if ($new_value != $cur_value && isset($status_list[$new_value])) {
						// Check if field has changed using FieldUpdateLock
						if (FieldUpdateLock::isChanged($deal['ID'], 'deal', $d_field_code, $deal[$d_field_code])) {
							$new_value = $status_list[$new_value];
							$shipment = self::getShipment($order);
							if ($shipment) {
								$shipment->setField('STATUS_ID', $new_value);
							}
							$has_changes = true;
							$f_has_changes = true;
						}
					}
					break;
				case 'DELIVERY_ALLOW':
					$cur_value = $order_data[$o_field_type];
					if ($new_value != $cur_value) {
						// Check if field has changed using FieldUpdateLock
						if (FieldUpdateLock::isChanged($deal['ID'], 'deal', $d_field_code, $deal[$d_field_code])) {
							$shipment = self::getShipment($order);
							if ($shipment) {
								if ($new_value == 'Y') {
									$shipment->allowDelivery();
								} else {
									$shipment->disallowDelivery();
								}
							}
							$has_changes = true;
							$f_has_changes = true;
						}
					}
					break;
				case 'DELIVERY_DEDUCTED':
					$cur_value = $order_data[$o_field_type];
					if ($new_value != $cur_value) {
						// Check if field has changed using FieldUpdateLock
						if (FieldUpdateLock::isChanged($deal['ID'], 'deal', $d_field_code, $deal[$d_field_code])) {
							$shipment = self::getShipment($order);
							if ($shipment) {
								$shipment->setField('DEDUCTED', $new_value);
							}
							$has_changes = true;
							$f_has_changes = true;
						}
					}
					break;
				case 'PAY_TYPE':
					$new_pay_id = false;
					$res = \Bitrix\Sale\Internals\PaySystemActionTable::getList([
						'filter' => ['NAME' => $new_value, 'ACTIVE' => 'Y']
					]);
					if ($row = $res->fetch()) {
						$new_pay_id = $row['ID'];
						$new_pay_name = $row['NAME'];
					}
					if ($new_pay_id && $new_pay_id != $order_data['PAY_TYPE_ID']) {
						// Check if field has changed using FieldUpdateLock
						if (FieldUpdateLock::isChanged($deal['ID'], 'deal', $d_field_code, $deal[$d_field_code])) {
							$payment_collection = $order->getPaymentCollection();
							$payment = $payment_collection->current();
							if (is_object($payment)) {
								$payment->setFields([
									'PAY_SYSTEM_ID'   => $new_pay_id,
									'PAY_SYSTEM_NAME' => $new_pay_name,
								]);
								$has_changes = true;
								$f_has_changes = true;
							}
						}
					}
					break;
				case 'PAY_STATUS':
					$cur_value = $order_data['IS_PAID'] ? 'Y' : 'N';
					if ($new_value != $cur_value) {
						// Check if field has changed using FieldUpdateLock
						if (FieldUpdateLock::isChanged($deal['ID'], 'deal', $d_field_code, $deal[$d_field_code])) {
							$payments = $order->getPaymentCollection();
							foreach ($payments as $payment) {
								$payment->setPaid($new_value);
								$payment->save();
							}
							$has_changes = true;
							$f_has_changes = true;
						}
					}
					break;
				case 'COMMENTS':
				case 'USER_DESCRIPTION':
					if ($new_value != $order_data[$o_field_type]) {
						// Check if field has changed using FieldUpdateLock
						if (FieldUpdateLock::isChanged($deal['ID'], 'deal', $d_field_code, $deal[$d_field_code])) {
							$order->setField($o_field_type, $new_value);
							$has_changes = true;
							$f_has_changes = true;
						}
					}
					break;
				default:
			}
			if ($f_has_changes) {
				\SProdIntegration::Log('(updateOrderParams) order ' . $order_data['ID'] . ' new ' . $o_field_type . ': ' . print_r($new_value, true));
			}
		}

		return $has_changes;
	}

	/**
	 * Update order products
	 */
	public static function updateProducts(array $deal_info, array $order_data, $profile, &$order, $new_order=false) {
		$has_changes = false;
		$deal_prod_rows = $deal_info['products'];
		$sync_type = Settings::get("products_sync_type");
		$site = \SProdIntegration::getSiteDef();
		$site_id = $profile['neworder']['site_code'] ?? $site['LID'];
		// Get order products
		$order_prod_rows = [];
		if (!empty($order_data['PRODUCTS'])) {
			foreach ($order_data['PRODUCTS'] as $item) {
				$order_prod_rows[$item['PRODUCT_ID']] = $item;
			}
		}
		\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' order_prod_rows ' . print_r($order_prod_rows, true));
		\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' deal_prod_rows ' . print_r($deal_prod_rows, true));
		// NEW SYNC MODE
		if ($sync_type == 'find_new') {
			// List of delivery CRM products
			$deliv_prods = [];
			if (Settings::get('products_deliv_prod_active')) {
				$deliv_prods = (array) Settings::get('products_deliv_prod_list', true);
			}
			// Get order products
			$store_prod_ids = array_keys($order_prod_rows);
			\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' store_prod_ids ' . print_r($store_prod_ids, true));
			// CRM product IDs by store IDs
			$prods_store_crm_ids = CrmProducts::getIDsByStoreIDs($store_prod_ids);
			\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' prods_store_crm_ids ' . print_r($prods_store_crm_ids, true));
			// Create basket
			$basket = $order->getBasket();
			if (!$basket) {
				$site_def = \SProdIntegration::getSiteDef();
				$site_id = $site_def['LID'];
				$basket = Sale\Basket::create($site_id);
				$fuser_id = Sale\Fuser::getIdByUserId($order->getUserId());
				$basket->setFUserId($fuser_id);
			}
			// Find changes
			if (!empty($basket)) {
				foreach ($basket as $basket_item) {
					$order_prod_row = $order_prod_rows[$basket_item->getProductId()];
					$deal_prod_row = false;
					// Find the corresponding string
					foreach ($deal_prod_rows as $prod_row) {
						if ($prod_row['PRODUCT_ID'] && $prod_row['PRODUCT_ID'] == $prods_store_crm_ids[$order_prod_row['PRODUCT_ID']]) {
							$deal_prod_row = $prod_row;
							break;
						}
					}
					// Change row
					if ($deal_prod_row) {
						\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' product ' . $basket_item->getProductId() . ' found');
//					$deal_prod_row['PRICE'] = $deal_prod_row['PRICE_EXCLUSIVE'];
						$cmpr_fields = ['QUANTITY', 'PRICE', 'PRICE_BRUTTO']; //, 'TAX_RATE'
						foreach ($cmpr_fields as $deal_field) {
							switch ($deal_field) {
								case 'PRICE_BRUTTO':
									$order_field = 'BASE_PRICE';
									break;
								case 'TAX_RATE':
									$order_field = 'VAT_RATE';
									break;
								case 'TAX_INCLUDED':
									$order_field = 'VAT_INCLUDED';
									break;
								default:
									$order_field = $deal_field;
							}
							$deal_value = $deal_prod_row[$deal_field];
							$order_value = $order_prod_row[$order_field];
							if ($deal_field == 'PRICE_BRUTTO') {
								$deal_value = $deal_prod_row['PRICE'] + $deal_prod_row['DISCOUNT_SUM'];
							}
							if (in_array($deal_field, ['QUANTITY', 'PRICE', 'PRICE_BRUTTO', 'TAX_RATE'])) {
								$deal_value = round(floatval($deal_value), 3);
								$order_value = round(floatval($order_value), 3);
							}
							if ($deal_field == 'TAX_RATE') {
								$deal_value *= 0.01;
							}
							\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' product ' . $basket_item->getProductId() . ' compare ' . $order_field . ': ' . $deal_value . ' <> ' . $order_value);
							if ($deal_value != $order_value) {
								if ($deal_field == 'PRICE') {
									$basket_item->setField('CUSTOM_PRICE', 'Y');
								}
								$basket_item->setField($order_field, $deal_value);
								$has_changes = true;
								\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' product ' . $basket_item->getProductId() . ' new ' . $order_field . ' ' . floatval($deal_value));
							}
						}
					} // Delete row
					else {
						\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' product ' . $basket_item->getProductId() . ' not found in deal');
						if (Settings::get("products_sync_allow_delete")) {
							if ( ! empty($deal_prod_rows)) {
								$basket_item->delete();
								$has_changes = true;
								\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' product ' . $basket_item->getProductId() . ' delete');
							}
						}
					}
				}
			}
			// Block bug of B24
			if (empty($deal_prod_rows)) {
				UpdateLock::save($deal_info['deal']['ID'], 'basket_stoc', []);
				$has_changes = true;
			} else {
				// Add row
				// Get deal products
				$crm_prod_ids = [];
				foreach ($deal_prod_rows as $prod_row) {
					$crm_prod_ids[] = $prod_row['PRODUCT_ID'];
				}
				// CRM product IDs by store IDs
				$prods_crm_store_ids = StoreProducts::getIDsByCrmIDs($crm_prod_ids);
				\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' prods_crm_store_ids ' . print_r($prods_crm_store_ids, true));
				// Search new products
//				$shipment = self::getShipment($order, true);
//				$shipment_items = $shipment->getShipmentItemCollection();
				foreach ($deal_prod_rows as $deal_prod_row) {
					// If product not exist in the order product rows and not delivery product
					if (!in_array($deal_prod_row['PRODUCT_ID'], $prods_store_crm_ids)
					&& !in_array($deal_prod_row['PRODUCT_ID'], $deliv_prods)) {
						// If product exist in the store catalog
						$store_prod_id = $prods_crm_store_ids[$deal_prod_row['PRODUCT_ID']];
						if ($store_prod_id) {
							// Add product to basket
							$basket_item = $basket->createItem('catalog', $store_prod_id);
							$product = StoreProducts::getProduct($store_prod_id);
							$prod_fields = [
								'QUANTITY'               => $deal_prod_row['QUANTITY'],
								'CURRENCY'               => $deal_info['deal']['CURRENCY_ID'],
								'NAME'                   => $product['NAME'],
								'PRODUCT_PROVIDER_CLASS' => \Bitrix\Catalog\Product\Basket::getDefaultProviderName(),
								'PRICE'                  => str_replace('.', ',', $deal_prod_row['PRICE']),
								'CUSTOM_PRICE'           => 'Y',
								'VAT_RATE'               => $deal_prod_row['TAX_RATE'] * 0.01,
								'VAT_INCLUDED'           => $deal_prod_row['TAX_INCLUDED'],
								'LID'                    => $site_id,
							];
//						if ($ib_product['TYPE'] == \Bitrix\Catalog\ProductTable::TYPE_SET) {
//							$prod_fields['TYPE'] = \Bitrix\Sale\BasketItem::TYPE_SET;
//						}
							\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' product ' . $basket_item->getProductId() . ' new prod fields ' . print_r($prod_fields, true));
							$basket_item->setFields($prod_fields);
//							// Shipment
//							$shipment_item = $shipment_items->createItem($basket_item);
//							$shipment_item->setQuantity($basket_item->getQuantity());
							// Update basket
							if (!$new_order) {
								$refreshStrategy = \Bitrix\Sale\Basket\RefreshFactory::create(\Bitrix\Sale\Basket\RefreshFactory::TYPE_FULL);
								$basket->refresh($refreshStrategy);
							}
							$has_changes = true;
							\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' product ' . $basket_item->getProductId() . ' add');
						}
					}
				}
			}
			// Check FUSER_ID
			$fuser_id = Sale\Fuser::getIdByUserId($order->getUserId());
			if ($basket->getFUserId() != $fuser_id) {
				$basket->setFUserId($fuser_id);
			}
			// Save new basket
			if ($new_order) {
				$order->setBasket($basket);
				$basket->save();
			}
		} // OLD SYNC MODE
		else {
			// Find changes
			$basket = $order->getBasket();
			foreach ($basket as $basket_item) {
				$order_prod_row = $order_prod_rows[$basket_item->getProductId()];
				$deal_prod_row = false;
				// Find the corresponding string
				foreach ($deal_prod_rows as $prod_row) {
					if ($prod_row['PRODUCT_NAME'] == $order_prod_row['PRODUCT_NAME']) {
						$deal_prod_row = $prod_row;
						break;
					}
				}
				if ($deal_prod_row) {
					// Change quantity
					if ((int) $deal_prod_row['QUANTITY'] > 0 && (int) $deal_prod_row['QUANTITY'] != (int) $order_prod_row['QUANTITY']) {
						$basket_item->setField('QUANTITY', (int) $deal_prod_row['QUANTITY']);
						$has_changes = true;
						\SProdIntegration::Log('(updateOrderProducts) order ' . $order_data['ID'] . ' product ' . $basket_item->getProductId() . ' new quantity ' . (int) $deal_prod_row['QUANTITY']);
					}
				}
			}
		}

		return $has_changes;
	}

	/**
	 * Update other order properties
	 */
	public static function updateOther(array $deal, array $order_data, array $deal_info, $profile, &$order) {
		$has_changes = false;
		// Responsible user
		if (Settings::get('link_responsibles')) {
			$user_id = StoreUser::findByDealAssigned($deal_info);
			if ($user_id && $user_id != $order_data['RESPONSIBLE_ID']) {
				// Check if ASSIGNED_BY_ID field has changed using FieldUpdateLock
				if (FieldUpdateLock::isChanged($deal['ID'], 'deal', 'ASSIGNED_BY_ID', $deal['ASSIGNED_BY_ID'])) {
					$order->setField('RESPONSIBLE_ID', $user_id);
					$has_changes = true;
					\SProdIntegration::Log('(updateOrderOther) order ' . $order_data['ID'] . ' new responsible "' . $user_id . '"');
				}
			}
		}

		return $has_changes;
	}

	/**
	 * Basic data for new order
	 */
	public static function setCreateBasic(array $deal, array $order_data, array $deal_info, $profile, &$order) {
		$has_changes = true;
		// Basic values
		$pay_type = $profile['neworder']['buyer_type'];
		$order->setPersonTypeId($pay_type);
		$currency_code = CurrencyManager::getBaseCurrency();
		$order->setField('CURRENCY', $currency_code);
		$order->setField('XML_ID', $deal['ID']);
		$responsible_id = (int)$profile['neworder']['resp_def'];
		if ($responsible_id) {
			$order->setField('RESPONSIBLE_ID', $responsible_id);
		}
		// Payment method
		$pay_method = (int)$profile['neworder']['pay_method'];
		if ($pay_method) {
			$paymentCollection = $order->getPaymentCollection();
			$payment = $paymentCollection->createItem();
			$paySystemService = PaySystem\Manager::getObjectById($pay_method);
			$payment->setFields(array(
				'PAY_SYSTEM_ID'   => $paySystemService->getField("PAY_SYSTEM_ID"),
				'PAY_SYSTEM_NAME' => $paySystemService->getField("NAME"),
				'SUM'             => $order->getPrice(),
			));
		}
		return $has_changes;
	}

	public static function setShipment($order, $profile) {
		// Delivery method
		$shipment_collection = $order->getShipmentCollection();
		$deliv_method = (int)$profile['neworder']['delivery_method'];
		$delivery_id = $deliv_method ? : Delivery\Services\EmptyDeliveryService::getEmptyDeliveryServiceId();
		$shipment_collection->createItem(Delivery\Services\Manager::getObjectById($delivery_id));
		// Add delivery to basket
		$shipment = self::getShipment($order, true);
		$shipmentItemCollection = $shipment->getShipmentItemCollection();
		foreach ($order->getBasket() as $item) {
			$shipmentItem = $shipmentItemCollection->createItem($item);
			$shipmentItem->setQuantity($item->getQuantity());
		}
	}

	public static function getShipment($order, $allow_system=false) {
		$result = false;
		$shipment_collection = $order->getShipmentCollection();
		foreach ($shipment_collection as $shipment) {
			if (!$shipment->isSystem()) {
				$result = $shipment;
				break;
			}
		}
		if (!$result && $allow_system) {
			$shipment_collection = $order->getShipmentCollection();
			$result = $shipment_collection->current();
		}
		return $result;
	}

}
