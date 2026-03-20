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

class StockGenerator extends AbstractGenerator implements EntityGeneratorInterface
{
    /**
     * Get Entity Table
     *
     * @return string
     */
    public function getEntityTable(): string
    {
        return 'inventory_source';
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
        $prefix = strtolower((string) $this->configReader->getConfig('prefix'));
        $sourcesCount = (int) ($entityConfig['msi_sources_count'] ?? 0);
        $stockCount = (int) ($entityConfig['msi_stock_count'] ?? 0);

        if ($sourcesCount <= 0 && $stockCount <= 0) {
            return [];
        }

        $data = [];

        for ($i = 1; $i <= $sourcesCount; $i++) {
            $sourceCode = sprintf('%s_source_%d', $prefix, $i);
            $data['inventory_source'][] = [
                'source_code' => $sourceCode,
                'name' => sprintf('%s Source %d', ucfirst($prefix), $i),
                'enabled' => 1,
                'country_id' => 'US',
            ];
        }

        for ($i = 1; $i <= $stockCount; $i++) {
            $data['inventory_stock'][] = [
                'name' => sprintf('%s Stock %d', ucfirst($prefix), $i),
            ];
        }

        return $data;
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
}
