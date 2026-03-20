<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Task\Stock;

use Qoliber\CatalogGenerator\Api\Task\TaskInterface;
use Qoliber\CatalogGenerator\Sql\InsertMultipleOnDuplicate;
use Qoliber\CatalogGenerator\Task\AbstractTask;

class LinkSourcesTask extends AbstractTask implements TaskInterface
{
    /**
     * Run Task
     *
     * @return \Qoliber\CatalogGenerator\Api\Task\TaskInterface
     * @throws \Exception
     */
    public function runTask(): TaskInterface
    {
        $sourceCodes = $this->getSourceCodes();
        $stockIds = $this->getStockIds();

        if (empty($sourceCodes) || empty($stockIds)) {
            return $this;
        }

        $linkData = [];

        foreach ($stockIds as $stockId) {
            foreach ($sourceCodes as $sourceCode) {
                $linkData[] = [
                    'stock_id' => (int) $stockId,
                    'source_code' => $sourceCode,
                    'priority' => 1,
                ];
            }
        }

        $insert = new InsertMultipleOnDuplicate();

        foreach (array_chunk($linkData, 2500) as $dataBatch) {
            $prepareStatement = $insert->buildInsertQuery(
                'inventory_source_stock_link',
                array_keys($dataBatch[0]),
                count($dataBatch)
            );

            $this->connection->execute($prepareStatement, InsertMultipleOnDuplicate::flatten($dataBatch));
        }

        $this->createStockViews($stockIds);

        return $this;
    }

    /**
     * Create MSI inventory_stock_{id} views for each custom stock
     *
     * @param string[] $stockIds
     * @return void
     */
    private function createStockViews(array $stockIds): void
    {
        $conn = $this->connection->getConnection();

        foreach ($stockIds as $stockId) {
            $viewName = sprintf('inventory_stock_%d', $stockId);

            $conn->query(sprintf(
                'CREATE OR REPLACE VIEW `%s` AS
                SELECT
                    isi.sku,
                    SUM(isi.quantity) AS quantity,
                    IF(SUM(isi.status) > 0, 1, 0) AS is_salable,
                    cpe.entity_id AS product_id,
                    0 AS website_id,
                    %d AS stock_id
                FROM `%s` isi
                INNER JOIN `%s` issl
                    ON isi.source_code = issl.source_code AND issl.stock_id = %d
                INNER JOIN `%s` cpe
                    ON cpe.sku = isi.sku
                WHERE isi.status = 1
                GROUP BY isi.sku, cpe.entity_id',
                $viewName,
                (int) $stockId,
                $this->connection->getTableName('inventory_source_item'),
                $this->connection->getTableName('inventory_source_stock_link'),
                (int) $stockId,
                $this->connection->getTableName('catalog_product_entity')
            ));
        }
    }

    /**
     * Get all non-default source codes
     *
     * @return string[]
     */
    private function getSourceCodes(): array
    {
        $query = $this->connection->getConnection()->select()
            ->from($this->connection->getTableName('inventory_source'), ['source_code'])
            ->where('source_code != ?', 'default');

        return $this->connection->getConnection()->fetchCol($query);
    }

    /**
     * Get all non-default stock IDs
     *
     * @return string[]
     */
    private function getStockIds(): array
    {
        $query = $this->connection->getConnection()->select()
            ->from($this->connection->getTableName('inventory_stock'), ['stock_id'])
            ->where('stock_id != ?', 1);

        return $this->connection->getConnection()->fetchCol($query);
    }
}
