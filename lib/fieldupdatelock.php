<?php
/**
 * Field-based update lock for granular change tracking
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option;

class FieldUpdateLock
{
    /**
     * Save hash for specific field
     */
    public static function save($entity_id, $entity_type, $field, $value) {
        $hash = self::getHash($value);
        FieldLockTable::save([
            'type' => $entity_type,
            'entity_id' => $entity_id,
            'field' => $field,
            'hash' => $hash,
        ]);
        //\SProdIntegration::Log('(FieldUpdateLock) ' . $entity_type . ' id ' . $entity_id . ' field ' . $field . ' save hash ' . $hash);
        return true;
    }

    /**
     * Check if any fields are saved for this entity
     */
    public static function hasSavedFields($entity_id, $entity_type) {
        $records = FieldLockTable::getList([
            'filter' => [
                'type' => $entity_type,
                'entity_id' => $entity_id
            ],
            'limit' => 1
        ]);
        return !empty($records);
    }

    /**
     * Check if specific field has changed (without updating hash)
     */
    public static function isChanged($entity_id, $entity_type, $field, $value) {
        // If no fields are saved for this entity, consider it as changed (first sync)
        if (!self::hasSavedFields($entity_id, $entity_type)) {
            \SProdIntegration::Log('(FieldUpdateLock) ' . $entity_type . ' id ' . $entity_id . ' field ' . $field . ' first sync - considering as changed');
            return true;
        }

        $res = true;
        $records = FieldLockTable::getList([
            'filter' => [
                'type' => $entity_type,
                'entity_id' => $entity_id,
                'field' => $field
            ]
        ]);
        $old_hash = '';
        if (!empty($records)) {
            $old_hash = $records[0]['hash'];
        }
        $new_hash = self::getHash($value);
        if ($old_hash == $new_hash) {
            $res = false;
        }
        \SProdIntegration::Log('(FieldUpdateLock) old_hash ' . $old_hash . ' new_hash ' . $new_hash);
        \SProdIntegration::Log('(FieldUpdateLock) ' . $entity_type . ' id ' . $entity_id . ' field ' . $field . ' has ' . ($res ? 'changed' : 'no changes'));
        return $res;
    }

    /**
     * Check if any of the specified fields have changed (without updating hashes)
     */
    public static function hasAnyFieldChanged($entity_id, $entity_type, $fields_array) {
        // If no fields are saved for this entity, consider all fields as changed (first sync)
        if (!self::hasSavedFields($entity_id, $entity_type)) {
            \SProdIntegration::Log('(FieldUpdateLock) ' . $entity_type . ' id ' . $entity_id . ' first sync - all fields considered changed');
            return true;
        }

        $has_changes = false;
        foreach ($fields_array as $field => $value) {
            if (self::isChanged($entity_id, $entity_type, $field, $value)) {
                $has_changes = true;
                break; // Can exit early on first change
            }
        }
        return $has_changes;
    }

    /**
     * Save hashes for multiple fields
     */
    public static function saveMultiple($entity_id, $entity_type, $fields_array) {
        foreach ($fields_array as $field => $value) {
            self::save($entity_id, $entity_type, $field, $value);
        }
    }

    /**
     * Update all field hashes after successful data exchange
     */
    public static function updateHashesAfterSync($entity_id, $entity_type, $fields_array) {
        \SProdIntegration::Log('(FieldUpdateLock) Updating hashes after sync for ' . $entity_type . ' id ' . $entity_id);
        self::saveMultiple($entity_id, $entity_type, $fields_array);
    }

    /**
     * Delete all field hashes for an entity
     */
    public static function deleteAll($entity_id, $entity_type) {
        \SProdIntegration::Log('(FieldUpdateLock) Deleting all hashes for ' . $entity_type . ' id ' . $entity_id);
        FieldLockTable::delLock($entity_id, $entity_type);
    }

    protected static function getHash($value) {
        // Normalize boolean values to ensure consistent hashing
        if (is_bool($value)) {
            $value = $value ? 'Y' : 'N';
        }
        // Normalize empty values to false for consistency
        if ($value === '' || $value === null) {
            $value = 'N';
        }
        // Normalize numeric strings to proper types
        if (is_string($value) && is_numeric($value)) {
            $value = $value == '1' ? 'Y' : 'N';
        }
        $value_str = serialize($value);
        $hash = md5($value_str);
        return $hash;
    }
}
