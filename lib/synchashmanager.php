<?php
/**
 * Synchronization hash manager for field-based update tracking
 *
 * @mail support@s-production.online
 * @link s-production.online
 */

namespace SProduction\Integration;

use Bitrix\Main,
    Bitrix\Main\DB\Exception,
    Bitrix\Main\Config\Option,
    Bitrix\Sale;

class SyncHashManager
{
    /**
     * Update field hashes after successful synchronization (works for both directions)
     * Updates hashes for both entities involved in synchronization
     * For the source entity (the one that changed), uses provided data instead of loading fresh data
     */
    public static function updateHashesAfterSync($source_entity_id, $source_entity_type, $target_entity_id, $target_entity_type, $source_entity_data = null) {
        \SProdIntegration::Log('(SyncHashManager) Updating field hashes after sync: ' . $source_entity_type . '->' . $target_entity_type);

        // Update source entity hashes (use provided data if available)
        self::updateEntityHashes($source_entity_id, $source_entity_type, $source_entity_data);

        // Update target entity hashes (load fresh data)
        self::updateEntityHashes($target_entity_id, $target_entity_type);
    }

    /**
     * Update hashes for a specific entity
     * If entity_data is provided, uses it instead of loading fresh data (for source entities)
     */
    protected static function updateEntityHashes($entity_id, $entity_type, $entity_data = null) {
        if ($entity_type === 'deal') {
            if ($entity_data !== null) {
                // Use provided deal data
                $deal = $entity_data;
            } else {
                // Load fresh deal data
                $deals = PortalData::getDeal([$entity_id]);
                if (!empty($deals)) {
                    $deal = $deals[0];
                } else {
                    return; // No deal data available
                }
            }
            $deal_fields = self::getDealFieldsForHash($deal);
            FieldUpdateLock::updateHashesAfterSync($entity_id, $entity_type, $deal_fields);
        } elseif ($entity_type === 'order') {
            if ($entity_data !== null) {
                // Use provided order data
                $order_data = $entity_data;
            } else {
                // Load fresh order data
                $order = \Bitrix\Sale\Order::load($entity_id);
                if ($order) {
                    $order_data = StoreData::getOrderInfo($order);
                } else {
                    return; // No order data available
                }
            }
            $order_fields = self::getOrderFieldsForHash($order_data);
            FieldUpdateLock::updateHashesAfterSync($entity_id, $entity_type, $order_fields);
        }
    }

    /**
     * Get all deal fields for hash calculation
     */
    public static function getDealFieldsForHash($deal) {
        $fields = [];

        // Copy all deal fields
        foreach ($deal as $field => $value) {
            $fields[$field] = $value;
        }

        return $fields;
    }

    /**
     * Get all order fields for hash calculation (except products)
     */
    public static function getOrderFieldsForHash($order_data) {
        $fields = [];

        // Copy all order data fields except PRODUCTS and PROPERTIES
        foreach ($order_data as $field => $value) {
            if ($field !== 'PRODUCTS' && $field !== 'PROPERTIES') {
                $fields[$field] = $value;
            }
        }

        // Add properties as separate fields with PROPERTY_ prefix
        if (isset($order_data['PROPERTIES']) && is_array($order_data['PROPERTIES'])) {
            foreach ($order_data['PROPERTIES'] as $property) {
                if (isset($property['ID']) && isset($property['VALUE'])) {
                    $fields['PROPERTY_' . $property['ID']] = $property['VALUE'];
                }
            }
        }

        return $fields;
    }
}
