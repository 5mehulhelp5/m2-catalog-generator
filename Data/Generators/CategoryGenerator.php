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

use Magento\Framework\Exception\LocalizedException;
use Qoliber\CatalogGenerator\Api\EntityGeneratorInterface;

class CategoryGenerator extends AbstractGenerator implements EntityGeneratorInterface
{
    /** @var string  */
    private const ENTITY_TABLE = 'catalog_category_entity';

    /**
     * Get Entity Table
     *
     * @return string
     */
    public function getEntityTable(): string
    {
        return self::ENTITY_TABLE;
    }

    /**
     * Generate Entities
     *
     * @param int|string $count
     * @param string $entityType
     * @param mixed[] $entityConfig
     * @return mixed[][]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function generateEntities(int|string $count, string $entityType, array $entityConfig = []): array
    {
        if (is_int($count)) {
            throw new LocalizedException(__('Count parameter for categories must be a string.'));
        }

        $categoryArray = [$this->getEntityTable() => []];
        $categoryId = 0;
        $categoryTreeSizes = explode('/', $count);
        $categoryTreeSizes = array_map('intval', $categoryTreeSizes);
        $this->generateCategoryArray(
            $categoryTreeSizes,
            $categoryArray[$this->getEntityTable()],
            $categoryId,
            0,
            '',
            0
        );

        return $this->generateAttributes($categoryArray, $entityConfig);
    }

    /**
     * Populate Attributes
     *
     * @param mixed[] $entityConfig
     * @param int $entityId
     * @return mixed[]
     */
    public function populateAttributes(array $entityConfig, int $entityId): array
    {
        $dataPopulator = $this->getDataPopulator('category/attributes');

        if (!$dataPopulator) {
            return [];
        }

        $categoryAttributeData = [];

        foreach ($entityConfig['attributes'] as $attributeCode => $attributeValue) {
            $attributeData = $dataPopulator->getAttributeData(
                (string) $this->getEntityTypeId('catalog_category'),
                $attributeCode
            );

            if (empty($attributeData)) {
                continue;
            }

            $categoryAttributeTable = $this->resourceConnection->getConnection()->getTableName(
                sprintf('%s_%s', $this->getEntityTable(), $attributeData['backend_type'])
            );

            $categoryAttributeData[$categoryAttributeTable][] = [
                'attribute_id' => $attributeData['attribute_id'],
                'store_id' => 0,
                'entity_id' => $entityId,
                'value' => $this->getAttributeValue($attributeValue),
            ];
        }

        return $categoryAttributeData;
    }

    /**
     * Generate Category Array
     *
     * @param int[]|string[] $levels
     * @param string[]|int[] $data
     * @param int $categoryID
     * @param int $parentID
     * @param string $currentPath
     * @param int $level
     * @return void
     */
    private function generateCategoryArray(
        array $levels,
        array &$data,
        int &$categoryID,
        int $parentID,
        string $currentPath = '',
        int $level = 0
    ): void {
        if (empty($levels)) {
            return;
        }

        $totalChildren = 0;
        $currentLevelNodes = 1;
        $levelCount = count($levels);

        for ($i = 1; $i < $levelCount; $i++) {
            $currentLevelNodes *= $levels[$i];
            $totalChildren += $currentLevelNodes;
        }

        $count = array_shift($levels);

        for ($position = 1; $position <= $count; $position++) {
            $categoryID++;
            $newPath = $currentPath ? "$currentPath/$categoryID" : "$categoryID";

            $data[] = [
                'entity_id' => $categoryID,
                'attribute_set_id' => $this->getDefaultAttributeSetId('catalog_category'),
                'parent_id' => $parentID,
                'path' => $newPath,
                'position' => $position,
                'level' => $level,
                'children_count' => $totalChildren,
            ];

            $this->generateCategoryArray($levels, $data, $categoryID, $categoryID, $newPath, $level+1);
        }
    }
}
