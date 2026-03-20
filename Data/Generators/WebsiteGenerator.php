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

class WebsiteGenerator extends AbstractGenerator implements EntityGeneratorInterface
{
    /** @var string  */
    public const STORE_WEBSITE_TABLE = 'store_website';

    /** @var string  */
    public const STORE_TABLE = 'store';

    /** @var string  */
    public const STORE_GROUP_TABLE = 'store_group';

    /**
     * Get Entity Table
     *
     * @return string
     */
    public function getEntityTable(): string
    {
        return '';
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
        $rootCategoryIds = $this->getRootCategories();

        $data = [
            'store_website' => [],
            'store_group' => [],
            'store' => []
        ];
        $storeId = 1;

        for ($i = 1; $i <= $count; $i++) {
            $rootCategoryId = $rootCategoryIds[$i-1] ?? (int) end($rootCategoryIds);
            $name = sprintf(
                '%s %d',
                $this->configReader->getConfig('prefix'),
                $i
            );

            $data['store_website'][] = $this->prepareWebsite($i, $name);
            $data['store_group'][] = $this->prepareStoreGroup($i, $name, $storeId, $rootCategoryId);

            for ($j = 0; $j < $entityConfig['stores_per_website']; $j++) {
                $data['store'][] = $this->prepareStoreView($storeId, $i);
                $storeId++;
            }
        }

        return $data;
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
        return [];
    }

    /**
     * Prepare Websites
     *
     * @param mixed $i
     * @param string $name
     * @return mixed[]
     */
    private function prepareWebsite(mixed $i, string $name): array
    {
        return [
            'website_id' => $i,
            'code' => sprintf(
                '%s_website_%d',
                strtolower($this->configReader->getConfig('prefix')),
                $i
            ),
            'name' => $name,
            'sort_order' => $i,
            'default_group_id' => $i,
            'is_default' => $i === 1 ? 1 : 0
        ];
    }

    /**
     * Prepare Store Group
     *
     * @param int $i
     * @param string $name
     * @param int $storeId
     * @param string|int $rootCategoryId
     * @return string[]|int[]
     */
    private function prepareStoreGroup(int $i, string $name, int $storeId, string|int $rootCategoryId): array
    {
        return [
            'group_id' => $i,
            'website_id' => $i,
            'name' => $name,
            'root_category_id' => $rootCategoryId,
            'default_store_id' => $storeId,
            'code' => sprintf(
                '%s_store_%d',
                strtolower($this->configReader->getConfig('prefix')),
                $i
            ),
        ];
    }

    /**
     * Prepare Stores
     *
     * @param int $storeId
     * @param int $i
     * @return string[]|int[]
     */
    private function prepareStoreView(int $storeId, int $i): array
    {
        return [
            'store_id' => $storeId,
            'code' => $storeId === 1
                ? 'default'
                : sprintf(
                    '%s_storeview_%d',
                    strtolower($this->configReader->getConfig('prefix')),
                    $storeId
                ),
            'website_id' => $i,
            'group_id' => $i,
            'name' => sprintf(
                '%s %d',
                $this->configReader->getConfig('prefix'),
                $storeId
            ),
            'sort_order' => 0,
            'is_active' => 1,
        ];
    }

    /**
     * Get Root Categories
     *
     * @return string[]
     */
    private function getRootCategories(): array
    {
        $query = $this->resourceConnection->getConnection()->select()
            ->from($this->resourceConnection->getConnection()->getTableName('catalog_category_entity'))
            ->where('level = ?', 1);

        return $this->resourceConnection->getConnection()->fetchCol($query);
    }
}
