<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Data\Generators\Product;

use Magento\Framework\App\ResourceConnection;
use Qoliber\CatalogGenerator\Api\DataGeneratorInterface;
use Qoliber\CatalogGenerator\Service\ConfigurableAttributeCombinator;
use Qoliber\CatalogGenerator\Sql\Connection;

class ConfigurableProductDataGenerator implements DataGeneratorInterface
{
    /**
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Qoliber\CatalogGenerator\Service\ConfigurableAttributeCombinator $combinator
     * @param \Qoliber\CatalogGenerator\Sql\Connection $sqlConnection
     * @param mixed[] $generatedAttributes
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ConfigurableAttributeCombinator $combinator,
        private readonly Connection $sqlConnection,
        private array $generatedAttributes = []
    ) {
    }

    /**
     * Generate Required Data
     *
     * @param string $prefix
     * @param mixed[] $options
     * @return mixed[]
     */
    public function generateRequiredData(string $prefix, array $options): array
    {
        $result = [];
        $attributeIds = [];

        if ($options && !$this->generatedAttributes) {
            $eavAttributeTable = $this->resourceConnection
                ->getTableName('eav_attribute');
            $eavAttributeSetTable = $this->resourceConnection
                ->getTableName('eav_attribute_set');
            $eavAttributeGroupTable = $this->resourceConnection
                ->getTableName('eav_attribute_group');
            $eavAttributeOptionTable = $this->resourceConnection
                ->getTableName('eav_attribute_option');
            $eavAttributeOptionValueTable = $this->resourceConnection
                ->getTableName('eav_attribute_option_value');
            $catalogEavAttributeTable = $this->resourceConnection
                ->getTableName('catalog_eav_attribute');

            $connection = $this->resourceConnection->getConnection();
            list($attributeCount, $attributeOptionCount) = $options;

            for ($i = 1; $i <= $attributeCount; $i++) {
                $attributeCode = sprintf('%s_%s_%d', $prefix, 'dropdown_attribute', $i);

                $productEntityTypeId = $this->sqlConnection->getEntityTypeId('catalog_product');

                $connection->insert($eavAttributeTable, [
                    'entity_type_id' => $productEntityTypeId,
                    'attribute_code' =>$attributeCode,
                    'frontend_input' => 'select',
                    'backend_type' => 'int',
                    'is_required' => 0,
                    'is_user_defined' => 1,
                    'frontend_label' => sprintf('%s %d', 'Config Attribute', $i)
                ]);
                // @phpstan-ignore-next-line
                $attributeId = $connection->lastInsertId();
                $attributeIds[] = $attributeId;
                $defaultAttributeSetId = $connection->fetchOne(
                    "SELECT attribute_set_id FROM $eavAttributeSetTable WHERE entity_type_id = :entityTypeId
                                     AND attribute_set_name = 'Default'",
                    ['entityTypeId' => $productEntityTypeId]
                );

                $defaultAttributeGroupId = $connection->fetchOne(
                    "SELECT attribute_group_id FROM $eavAttributeGroupTable WHERE attribute_set_id = :setId
                                     AND attribute_group_name = 'General'",
                    ['setId' => $defaultAttributeSetId]
                );

                $connection->insert('eav_entity_attribute', [
                    'entity_type_id' => $productEntityTypeId,
                    'attribute_set_id' => $defaultAttributeSetId,
                    'attribute_group_id' => $defaultAttributeGroupId,
                    'attribute_id' => $attributeId,
                    'sort_order' => 0,
                ]);

                $connection->insert($catalogEavAttributeTable, [
                    'attribute_id' => $attributeId,
                    'is_visible' => 1,
                    'is_searchable' => 0,
                    'is_filterable' => 1,
                    'is_comparable' => 0,
                    'is_visible_on_front' => 1,
                    'is_html_allowed_on_front' => 0,
                    'is_used_for_price_rules' => 0,
                    'is_filterable_in_search' => 0,
                    'used_in_product_listing' => 1,
                    'used_for_sort_by' => 0,
                    'apply_to' => 'simple,configurable,virtual'
                ]);

                $attributeValues = [];
                for ($j = 1; $j <= $attributeOptionCount; $j++) {
                    $connection->insert($eavAttributeOptionTable, [
                        'attribute_id' => $attributeId,
                        'sort_order' => $j,
                    ]);

                    // @phpstan-ignore-next-line
                    $optionId = $connection->lastInsertId();
                    $optionLabel = sprintf('%s %d', 'value', $j);
                    $connection->insert($eavAttributeOptionValueTable, [
                        'option_id' => $optionId,
                        'store_id' => 0,
                        'value' => $optionLabel
                    ]);

                    $attributeValues[$optionId] = $optionLabel;
                }

                $result[$attributeCode] = $attributeValues;
            }

            $this->generatedAttributes = [
                'attributeIds' => $attributeIds,
                'attributeData' => $result,
            ];
        }

        return $this->generatedAttributes;
    }

    /**
     * Populate required Tables
     *
     * @param int $parentProductId
     * @param mixed[] $dataOptions
     * @return mixed[]
     */
    public function populateRequiredTables(int $parentProductId, array $dataOptions): array
    {
        $connection = $this->resourceConnection->getConnection();
        $superAttributeTable = $this->resourceConnection
            ->getTableName('catalog_product_super_attribute');
        $superAttributeLabelTable = $this->resourceConnection
            ->getTableName('catalog_product_super_attribute_label');
        $superLinkTable = $this->resourceConnection
            ->getTableName('catalog_product_super_link');
        $productRelationTable = $this->resourceConnection
            ->getTableName('catalog_product_relation');

        $position = 0;
        foreach ($dataOptions['attribute_ids'] as $attributeId) {
            $connection->insert($superAttributeTable, [
                'product_id' => $parentProductId,
                'attribute_id' => (int) $attributeId,
                'position' => $position
            ]);
            $position++;
            // @phpstan-ignore-next-line
            $productSuperAttributeId = $connection->lastInsertId();

            $connection->insert($superAttributeLabelTable, [
                'product_super_attribute_id' => $productSuperAttributeId,
                'store_id' => 0,
                'use_default' => 0,
                'value' => sprintf('Option %d', $position)
            ]);
        }

        foreach ($dataOptions['child_ids'] as $childProductId) {
            $connection->insert($superLinkTable, [
                'parent_id' => $parentProductId,
                'product_id' => (int) $childProductId,
            ]);

            $connection->insert($productRelationTable, [
                'parent_id' => $parentProductId,
                'child_id' => (int) $childProductId,
            ]);
        }

        return [];
    }

    /**
     * Get Child Product Variations
     *
     * @param int $parentProductId
     * @param string $prefix
     * @param mixed[] $options
     * @return mixed[]
     * @throws \Random\RandomException
     */
    public function getChildProductVariations(int $parentProductId, string $prefix, array $options): array
    {
        $childProducts = [];
        $productId = $parentProductId + 1;
        $entityRequiredData = $this->generateRequiredData($prefix, $options);

        if ($entityRequiredData) {
            $newAttributes = $entityRequiredData['attributeData'];
            $attributeIds = $entityRequiredData['attributeIds'];
            $attributeVariations = $this->combinator->generateCombinations($newAttributes);

            foreach ($attributeVariations as $attributeVariation) {
                $childProducts[] = [
                    'entity_id' => $productId++,
                    'attributes' => [
                        'visibility' => 1,
                        'price' => random_int(9, 999),
                        ...$attributeVariation
                    ]
                ];
            }

            $this->populateRequiredTables(
                $parentProductId,
                [
                    'attribute_ids' => $attributeIds,
                    'child_ids' => array_column($childProducts, 'entity_id'),
                ]
            );

            return $childProducts;
        }

        return [];
    }
}
