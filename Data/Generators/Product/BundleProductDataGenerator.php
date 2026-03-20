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

class BundleProductDataGenerator implements DataGeneratorInterface
{
    /**
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /** @var int|null */
    private ?int $entityTypeId = null;

    /** @var array<string, int> */
    private array $attributeIdCache = [];

    /**
     * Generate Required Data
     *
     * @param string $prefix
     * @param mixed[] $options
     * @return mixed[]
     */
    public function generateRequiredData(string $prefix, array $options): array
    {
        return [];
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
        $bundleOptionTable = $this->resourceConnection
            ->getTableName('catalog_product_bundle_option');
        $bundleOptionTableValue = $this->resourceConnection
            ->getTableName('catalog_product_bundle_option_value');
        $bundleSelectionTable = $this->resourceConnection
            ->getTableName('catalog_product_bundle_selection');

        $position = 1;
        $productId = $parentProductId + 1;

        foreach ($dataOptions as $bundleOptionType => $bundleOptionCount) {
            $connection->insert($bundleOptionTable, [
                'parent_id' => $parentProductId,
                'required' => 1,
                'position' => $position++,
                'type' => $bundleOptionType,
            ]);

            // @phpstan-ignore-next-line
            $bundleOptionId = $connection->lastInsertId();
            $connection->insert($bundleOptionTableValue, [
                'option_id' => $bundleOptionId,
                'store_id' => 0,
                'title' => sprintf('Bundle %s option', $bundleOptionType),
                'parent_product_id' => $parentProductId,
            ]);

            $productPosition = 1;

            for ($i = 0; $i < $bundleOptionCount; $i++) {
                $connection->insert($bundleSelectionTable, [
                    'option_id' => $bundleOptionId,
                    'parent_product_id' => $parentProductId,
                    'product_id' => $productId++, // mapping the same as in the bottom column
                    'position' => $productPosition++,
                    'is_default' => 0,
                    'selection_qty' => 1,
                ]);
            }
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

        foreach ($options as $bundleOptionType => $bundleOptionCount) {
            for ($i = 0; $i < $bundleOptionCount; $i++) {
                $childProducts[] = [
                    'entity_id' => $productId++,
                    'attributes' => [
                        'visibility' => 1,
                        'price' => random_int(9, 999)
                    ]
                ];
            }
        }

        $this->populateRequiredTables(
            $parentProductId,
            $options
        );

        $this->insertBundleAttributes($parentProductId);
        $this->insertProductRelations($parentProductId, $childProducts);

        return $childProducts;
    }

    /**
     * Insert bundle-specific EAV attributes
     *
     * @param int $parentProductId
     * @return void
     */
    private function insertBundleAttributes(int $parentProductId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_entity_int');

        $bundleAttrs = [
            'price_type' => 0,
            'sku_type' => 0,
            'weight_type' => 0,
            'price_view' => 0,
            'shipment_type' => 1,
        ];

        foreach ($bundleAttrs as $code => $value) {
            $attributeId = $this->getBundleAttributeId($code);

            if ($attributeId) {
                $connection->insert($table, [
                    'attribute_id' => $attributeId,
                    'store_id' => 0,
                    'entity_id' => $parentProductId,
                    'value' => $value,
                ]);
            }
        }
    }

    /**
     * Insert product relations for bundle parent-child
     *
     * @param int $parentProductId
     * @param mixed[][] $childProducts
     * @return void
     */
    private function insertProductRelations(int $parentProductId, array $childProducts): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_relation');

        foreach ($childProducts as $child) {
            $connection->insert($table, [
                'parent_id' => $parentProductId,
                'child_id' => $child['entity_id'],
            ]);
        }
    }

    /**
     * Get attribute ID for bundle product attribute
     *
     * @param string $attributeCode
     * @return int
     */
    private function getBundleAttributeId(string $attributeCode): int
    {
        if (!isset($this->attributeIdCache[$attributeCode])) {
            if ($this->entityTypeId === null) {
                $connection = $this->resourceConnection->getConnection();
                $this->entityTypeId = (int) $connection->fetchOne(
                    $connection->select()
                        ->from($connection->getTableName('eav_entity_type'), ['entity_type_id'])
                        ->where('entity_type_code = ?', 'catalog_product')
                );
            }

            $connection = $this->resourceConnection->getConnection();
            $this->attributeIdCache[$attributeCode] = (int) $connection->fetchOne(
                $connection->select()
                    ->from($connection->getTableName('eav_attribute'), ['attribute_id'])
                    ->where('entity_type_id = ?', $this->entityTypeId)
                    ->where('attribute_code = ?', $attributeCode)
            );
        }

        return $this->attributeIdCache[$attributeCode];
    }
}
