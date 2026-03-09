<?php
namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\Entity,
    Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Class FieldLockTable
 *
 * Fields:
 * <ul>
 * <li> id int mandatory
 * <li> type string(255) mandatory
 * <li> entity_id string(255) mandatory
 * <li> field string(255) mandatory
 * <li> time int mandatory
 * <li> hash string(255) mandatory
 * </ul>
 *
 * @package SProduction\Integration
 **/

class FieldLockTable extends Main\Entity\DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'sprod_integration_field_locks';
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
                'title' => Loc::getMessage('SP_CI_FIELD_LOCK_ENTITY_ID_FIELD'),
            ]),
            new Entity\StringField('type', [
                'required' => true,
                'validation' => array(__CLASS__, 'validateType'),
                'title' => Loc::getMessage('SP_CI_FIELD_LOCK_ENTITY_TYPE_FIELD'),
            ]),
            new Entity\StringField('entity_id', [
                'required' => true,
                'validation' => array(__CLASS__, 'validateEntityId'),
                'title' => Loc::getMessage('SP_CI_FIELD_LOCK_ENTITY_ENTITY_ID_FIELD'),
            ]),
            new Entity\StringField('field', [
                'required' => true,
                'validation' => array(__CLASS__, 'validateField'),
                'title' => Loc::getMessage('SP_CI_FIELD_LOCK_ENTITY_FIELD_FIELD'),
            ]),
            new Entity\IntegerField('time', [
                'required' => true,
                'title' => Loc::getMessage('SP_CI_FIELD_LOCK_ENTITY_TIME_FIELD'),
            ]),
            new Entity\StringField('hash', [
                'required' => true,
                'validation' => array(__CLASS__, 'validateHash'),
                'title' => Loc::getMessage('SP_CI_FIELD_LOCK_ENTITY_HASH_FIELD'),
            ]),
        );
    }

    /**
     * Returns validators for type field.
     *
     * @return array
     */
    public static function validateType()
    {
        return array(
            new Main\Entity\Validator\Length(null, 255),
        );
    }

    /**
     * Returns validators for entity_id field.
     *
     * @return array
     */
    public static function validateEntityId()
    {
        return array(
            new Main\Entity\Validator\Length(null, 255),
        );
    }

    /**
     * Returns validators for field field.
     *
     * @return array
     */
    public static function validateField()
    {
        return array(
            new Main\Entity\Validator\Length(null, 255),
        );
    }

    /**
     * Returns validators for hash field.
     *
     * @return array
     */
    public static function validateHash()
    {
        return array(
            new Main\Entity\Validator\Length(null, 255),
        );
    }

    public static function add(array $fields)
    {
        $res = false;
        $list = self::getList([
            'filter' => [
                'entity_id' => $fields['entity_id'],
                'type' => $fields['type'],
                'field' => $fields['field']
            ],
        ]);
        if (empty($list)) {
            if (!isset($fields['time'])) {
                $fields['time'] = time();
            }
            $result = parent::add($fields);
            if ($result->isSuccess()) {
                $res = $fields['time'];
            }
        }
        return $res;
    }

    public static function save(array $fields)
    {
        $list = self::getList([
            'filter' => [
                'entity_id' => $fields['entity_id'],
                'type' => $fields['type'],
                'field' => $fields['field'],
            ],
        ]);
        if (empty($list)) {
            if (!isset($fields['time'])) {
                $fields['time'] = time();
            }
            $res = parent::add($fields);
        } else {
            $id = $list[0]['id'];
            $res = parent::update($id, $fields);
        }
        return $res;
    }

    public static function getList(array $params = [])
    {
        $list = [];
        $result = parent::getList($params);
        while ($item = $result->fetch()) {
            $list[] = $item;
        }
        return $list;
    }

    public static function delLock($entity_id, $type, $field = null)
    {
        $filter = [
            'entity_id' => $entity_id,
            'type' => $type,
        ];
        if ($field !== null) {
            $filter['field'] = $field;
        }

        $list = self::getList(['filter' => $filter]);
        foreach ($list as $item) {
            parent::delete($item['id']);
        }
    }
}
