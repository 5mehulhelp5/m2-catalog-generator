<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Data\Generators;

use Qoliber\CatalogGenerator\Api\EntityGeneratorInterface;

class CustomAttributeGenerator extends AbstractGenerator implements EntityGeneratorInterface
{
    /**
     * Get Entity Table
     *
     * @return string
     */
    public function getEntityTable(): string
    {
        return 'eav_attribute';
    }

    /**
     * Generate Entities. return entity array
     *
     * @param int|string $count
     * @param string $entityType
     * @param mixed[] $entityConfig
     * @return mixed[][]
     */
    public function generateEntities(int|string $count, string $entityType, array $entityConfig = []): array
    {
        $conn = $this->resourceConnection->getConnection();
        $entityTypeId = $this->getEntityTypeId('catalog_product');
        $attributeSetId = $this->getDefaultAttributeSetId('catalog_product');
        $defaultGroupId = $this->getDefaultAttributeGroupId($attributeSetId);

        $attributeTypes = ['dropdown', 'multiselect'];

        foreach ($attributeTypes as $type) {
            $typeConfig = $entityConfig[$type] ?? null;

            if (!$typeConfig) {
                continue;
            }

            $attrCount = (int) ($typeConfig['count'] ?? 0);
            $optionsPerAttribute = (int) ($typeConfig['options_per_attribute'] ?? 5);
            $isSearchable = (int) ($typeConfig['searchable'] ?? 0);
            $isFilterable = (int) ($typeConfig['filterable'] ?? 0);
            $backendType = $type === 'multiselect' ? 'varchar' : 'int';
            $frontendInput = $type === 'multiselect' ? 'multiselect' : 'select';
            $backendModel = $type === 'multiselect'
                ? 'Magento\\Eav\\Model\\Entity\\Attribute\\Backend\\ArrayBackend'
                : null;

            for ($i = 1; $i <= $attrCount; $i++) {
                $attributeCode = sprintf('%s_%d', $type, $i);

                $conn->insert(
                    $conn->getTableName('eav_attribute'),
                    [
                        'entity_type_id' => $entityTypeId,
                        'attribute_code' => $attributeCode,
                        'backend_type' => $backendType,
                        'backend_model' => $backendModel,
                        'frontend_input' => $frontendInput,
                        'frontend_label' => sprintf('%s %d', ucfirst($type), $i),
                        'is_required' => 0,
                        'is_user_defined' => 1,
                        'source_model' => $type === 'dropdown'
                            ? 'Magento\\Eav\\Model\\Entity\\Attribute\\Source\\Table'
                            : null,
                    ]
                );

                $attributeId = (int) $conn->lastInsertId();

                $conn->insert(
                    $conn->getTableName('catalog_eav_attribute'),
                    [
                        'attribute_id' => $attributeId,
                        'is_global' => 1,
                        'is_searchable' => $isSearchable,
                        'is_filterable' => $isFilterable,
                        'is_comparable' => 0,
                        'is_visible_on_front' => 1,
                        'is_html_allowed_on_front' => 0,
                        'is_filterable_in_search' => $isFilterable,
                        'used_in_product_listing' => 0,
                        'is_visible_in_advanced_search' => $isSearchable,
                        'is_used_for_promo_rules' => 0,
                    ]
                );

                $conn->insert(
                    $conn->getTableName('eav_entity_attribute'),
                    [
                        'entity_type_id' => $entityTypeId,
                        'attribute_set_id' => $attributeSetId,
                        'attribute_group_id' => $defaultGroupId,
                        'attribute_id' => $attributeId,
                        'sort_order' => 100 + $i,
                    ]
                );

                for ($j = 1; $j <= $optionsPerAttribute; $j++) {
                    $conn->insert(
                        $conn->getTableName('eav_attribute_option'),
                        [
                            'attribute_id' => $attributeId,
                            'sort_order' => $j,
                        ]
                    );

                    $optionId = (int) $conn->lastInsertId();

                    $conn->insert(
                        $conn->getTableName('eav_attribute_option_value'),
                        [
                            'option_id' => $optionId,
                            'store_id' => 0,
                            'value' => sprintf('%s %d Option %d', ucfirst($type), $i, $j),
                        ]
                    );
                }
            }
        }

        return [];
    }

    /**
     * Populate attributes
     *
     * @param mixed[] $entityConfig
     * @param int $entityId
     * @return mixed[]
     */
    public function populateAttributes(array $entityConfig, int $entityId): array
    {
        return [];
    }

    /**
     * Get default attribute group ID for an attribute set
     *
     * @param int $attributeSetId
     * @return int
     */
    private function getDefaultAttributeGroupId(int $attributeSetId): int
    {
        $conn = $this->resourceConnection->getConnection();

        return (int) $conn->fetchOne(
            $conn->select()
                ->from($conn->getTableName('eav_attribute_group'), ['attribute_group_id'])
                ->where('attribute_set_id = ?', $attributeSetId)
                ->order('sort_order ASC')
                ->limit(1)
        );
    }
}
