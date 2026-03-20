<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Task\Product;

use Qoliber\CatalogGenerator\Api\Task\TaskInterface;
use Qoliber\CatalogGenerator\Sql\InsertMultipleOnDuplicate;
use Qoliber\CatalogGenerator\Task\AbstractTask;

class AssignToTypeCategoriesTask extends AbstractTask implements TaskInterface
{
    /** @var string[] */
    private const TYPE_CATEGORY_MAP = [
        'configurable' => 'Configurable Products',
        'bundle' => 'Bundle Products',
        'grouped' => 'Grouped Products',
    ];

    /**
     * Run Task
     *
     * @return \Qoliber\CatalogGenerator\Api\Task\TaskInterface
     * @throws \Exception
     */
    public function runTask(): TaskInterface
    {
        $conn = $this->connection->getConnection();
        $parentCategoryId = $this->getRootChildId();
        $entityTypeId = $this->getEntityTypeId('catalog_category');
        $nameAttributeId = $this->getAttributeId($entityTypeId, 'name');
        $urlKeyAttributeId = $this->getAttributeId($entityTypeId, 'url_key');
        $urlPathAttributeId = $this->getAttributeId($entityTypeId, 'url_path');
        $isActiveAttributeId = $this->getAttributeId($entityTypeId, 'is_active');
        $includeInMenuAttributeId = $this->getAttributeId($entityTypeId, 'include_in_menu');
        $isAnchorAttributeId = $this->getAttributeId($entityTypeId, 'is_anchor');
        $attributeSetId = $this->getDefaultAttributeSetId();

        $maxEntityId = (int) $conn->fetchOne(
            $conn->select()->from(
                $this->connection->getTableName('catalog_category_entity'),
                ['MAX(entity_id)']
            )
        );

        $parentPath = (string) $conn->fetchOne(
            $conn->select()->from(
                $this->connection->getTableName('catalog_category_entity'),
                ['path']
            )->where('entity_id = ?', $parentCategoryId)
        );

        $parentLevel = (int) $conn->fetchOne(
            $conn->select()->from(
                $this->connection->getTableName('catalog_category_entity'),
                ['level']
            )->where('entity_id = ?', $parentCategoryId)
        );

        $typeCategoryIds = [];
        $position = 0;

        foreach (self::TYPE_CATEGORY_MAP as $typeId => $categoryName) {
            $maxEntityId++;
            $categoryId = $maxEntityId;
            $typeCategoryIds[$typeId] = $categoryId;
            $path = sprintf('%s/%d', $parentPath, $categoryId);
            $level = $parentLevel + 1;
            $urlKey = strtolower(str_replace(' ', '-', $categoryName));

            $conn->insert(
                $this->connection->getTableName('catalog_category_entity'),
                [
                    'entity_id' => $categoryId,
                    'attribute_set_id' => $attributeSetId,
                    'parent_id' => $parentCategoryId,
                    'path' => $path,
                    'position' => $position++,
                    'level' => $level,
                    'children_count' => 0,
                ]
            );

            $conn->insert(
                $this->connection->getTableName('catalog_category_entity_varchar'),
                [
                    'attribute_id' => $nameAttributeId,
                    'store_id' => 0,
                    'entity_id' => $categoryId,
                    'value' => $categoryName,
                ]
            );

            $conn->insert(
                $this->connection->getTableName('catalog_category_entity_varchar'),
                [
                    'attribute_id' => $urlKeyAttributeId,
                    'store_id' => 0,
                    'entity_id' => $categoryId,
                    'value' => $urlKey,
                ]
            );

            $conn->insert(
                $this->connection->getTableName('catalog_category_entity_varchar'),
                [
                    'attribute_id' => $urlPathAttributeId,
                    'store_id' => 0,
                    'entity_id' => $categoryId,
                    'value' => $urlKey,
                ]
            );

            $conn->insert(
                $this->connection->getTableName('catalog_category_entity_int'),
                [
                    'attribute_id' => $isActiveAttributeId,
                    'store_id' => 0,
                    'entity_id' => $categoryId,
                    'value' => 1,
                ]
            );

            $conn->insert(
                $this->connection->getTableName('catalog_category_entity_int'),
                [
                    'attribute_id' => $includeInMenuAttributeId,
                    'store_id' => 0,
                    'entity_id' => $categoryId,
                    'value' => 1,
                ]
            );

            $conn->insert(
                $this->connection->getTableName('catalog_category_entity_int'),
                [
                    'attribute_id' => $isAnchorAttributeId,
                    'store_id' => 0,
                    'entity_id' => $categoryId,
                    'value' => 1,
                ]
            );
        }

        $this->assignProductsToTypeCategories($typeCategoryIds);

        return $this;
    }

    /**
     * Get root child category ID (first category at level 1 or 2)
     *
     * @return int
     */
    private function getRootChildId(): int
    {
        $conn = $this->connection->getConnection();

        $result = $conn->fetchOne(
            $conn->select()->from(
                $this->connection->getTableName('catalog_category_entity'),
                ['entity_id']
            )->where('level = ?', 1)->order('entity_id ASC')->limit(1)
        );

        return (int) ($result ?: 2);
    }

    /**
     * Get default attribute set ID for catalog_category
     *
     * @return int
     */
    private function getDefaultAttributeSetId(): int
    {
        $conn = $this->connection->getConnection();

        return (int) $conn->fetchOne(
            $conn->select()
                ->from($this->connection->getTableName('eav_entity_type'), ['default_attribute_set_id'])
                ->where('entity_type_code = ?', 'catalog_category')
        );
    }

    /**
     * Assign products to their type categories
     *
     * @param array<string, int> $typeCategoryIds
     * @return void
     * @throws \Exception
     */
    private function assignProductsToTypeCategories(array $typeCategoryIds): void
    {
        $conn = $this->connection->getConnection();
        $insert = new InsertMultipleOnDuplicate();
        $productCategoryData = [];

        foreach ($typeCategoryIds as $typeId => $categoryId) {
            $productIds = $conn->fetchCol(
                $conn->select()->from(
                    $this->connection->getTableName('catalog_product_entity'),
                    ['entity_id']
                )->where('type_id = ?', $typeId)
            );

            $position = 0;
            foreach ($productIds as $productId) {
                $productCategoryData[] = [
                    'category_id' => $categoryId,
                    'product_id' => (int) $productId,
                    'position' => $position++,
                ];
            }
        }

        if (empty($productCategoryData)) {
            return;
        }

        foreach (array_chunk($productCategoryData, 2500) as $dataBatch) {
            $prepareStatement = $insert->buildInsertQuery(
                'catalog_category_product',
                array_keys($dataBatch[0]),
                count($dataBatch)
            );

            $this->connection->execute($prepareStatement, InsertMultipleOnDuplicate::flatten($dataBatch));
        }
    }
}
