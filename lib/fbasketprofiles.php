<?php
namespace SProduction\Integration;

use Bitrix\Main,
	Bitrix\Main\Entity,
	Bitrix\Main\Type,
	Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Class FbasketProfilesTable
 *
 * Fields:
 * <ul>
 * <li> id int mandatory
 * <li> name string(255) mandatory
 * <li> active string(4) mandatory
 * <li> site string(50) optional
 * <li> options string mandatory
 * <li> contacts string mandatory
 * <li> fields string mandatory
 * <li> statuses string mandatory
 * </ul>
 *
 * @package SProduction\Integration
 **/

class FbasketProfilesTable extends Main\Entity\DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'sprod_integration_fbasket_profiles';
	}

	/**
	 * Returns entity map definition.
	 *
	 * @return array
	 */
	public static function getMap()
	{
		return array(
			new Entity\IntegerField('id', [
				'primary' => true,
				'autocomplete' => true,
				'title' => Loc::getMessage('SP_CI_FBASKET_PROFILES_ENTITY_ID_FIELD'),
			]),
			new Entity\StringField('name', [
				'required' => true,
				'validation' => array(__CLASS__, 'validateName'),
				'title' => Loc::getMessage('SP_CI_FBASKET_PROFILES_ENTITY_NAME_FIELD'),
			]),
			new Entity\BooleanField('active', [
				'title' => Loc::getMessage('SP_CI_FBASKET_PROFILES_ENTITY_ACTIVE_FIELD'),
				'values' => ['', 'N', 'Y'],
				'default_value' => 'Y',
			]),
			new Entity\StringField('site', [
				'validation' => array(__CLASS__, 'validateSite'),
				'title' => Loc::getMessage('SP_CI_FBASKET_PROFILES_ENTITY_SITE_FIELD'),
			]),
			new Entity\StringField('options', [
				'title' => Loc::getMessage('SP_CI_FBASKET_PROFILES_ENTITY_OPTIONS_FIELD'),
			]),
			new Entity\StringField('contacts', [
				'title' => Loc::getMessage('SP_CI_FBASKET_PROFILES_ENTITY_CONTACTS_FIELD'),
			]),
			new Entity\StringField('fields', [
				'title' => Loc::getMessage('SP_CI_FBASKET_PROFILES_ENTITY_FIELDS_FIELD'),
			]),
			new Entity\StringField('statuses', [
				'title' => Loc::getMessage('SP_CI_FBASKET_PROFILES_ENTITY_STATUSES_FIELD'),
			]),
		);
	}

	/**
	 * Returns validators for name field.
	 *
	 * @return array
	 */
	public static function validateName()
	{
		return array(
			new Main\Entity\Validator\Length(null, 255),
		);
	}

	/**
	 * Returns validators for site field.
	 *
	 * @return array
	 */
	public static function validateSite()
	{
		return array(
			new Main\Entity\Validator\Length(null, 50),
		);
	}

	/**
	 * Add record with default values
	 */
	public static function add(array $fields)
	{
		if (!isset($fields['active'])) {
			$fields['active'] = 'Y';
		}
		$res = parent::add($fields);
		return $res;
	}

	/**
	 * Update record with serialization
	 */
	public static function update($id, array $fields)
	{
		foreach ($fields as $field => $value) {
			if (is_array($value)) {
				$fields[$field] = serialize($value);
			}
		}
		$res = parent::update($id, $fields);
		return $res;
	}

	/**
	 * Get list with unserialization
	 */
	public static function getList(array $params=[])
	{
		$list = [];
		$result = parent::getList($params);
		while ($item = $result->fetch()) {
			foreach ($item as $field => $value) {
				switch ($field) {
					case 'options':
					case 'contacts':
					case 'fields':
					case 'statuses':
						if (is_string($value) && !empty($value)) {
							$unserialized = @unserialize($value);
							$item[$field] = ($unserialized !== false || $value === 'b:0;') ? $unserialized : [];
						} else {
							$item[$field] = is_array($value) ? $value : [];
						}
						break;
				}
			}
			$list[] = $item;
		}
		return $list;
	}

	/**
	 * Get record by ID
	 */
	public static function getById($id) {
		$fields = false;
		$result = parent::getById($id);
		foreach ($result as $fields) {
			// Unserialize complex fields
			foreach ($fields as $field => $value) {
				switch ($field) {
					case 'options':
					case 'contacts':
					case 'fields':
					case 'statuses':
						if (is_string($value) && !empty($value)) {
							$unserialized = @unserialize($value);
							$fields[$field] = ($unserialized !== false || $value === 'b:0;') ? $unserialized : [];
						} else {
							$fields[$field] = is_array($value) ? $value : [];
						}
						break;
				}
			}
		}
		return $fields;
	}

	/**
	 * Get active profiles list
	 */
	public static function getActiveList() {
		return self::getList([
			'filter' => ['active' => 'Y'],
			'order' => ['name' => 'ASC']
		]);
	}

	/**
	 * Get profile by name
	 */
	public static function getByName($name) {
		$list = self::getList([
			'filter' => ['name' => $name],
			'limit' => 1
		]);
		return !empty($list) ? $list[0] : false;
	}
}