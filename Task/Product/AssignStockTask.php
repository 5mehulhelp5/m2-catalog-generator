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

class AssignStockTask extends AbstractProductTask implements TaskInterface
{
    /**
     * Run Task
     *
     * @return \Qoliber\CatalogGenerator\Api\Task\TaskInterface
     * @throws \Exception
     */
    public function runTask(): TaskInterface
    {
        $insert = new InsertMultipleOnDuplicate();
        $sourceCodes = $this->getSourceCodes();
        $productBatches = $this->connection->getEntityBatches(
            'entity_id',
            'catalog_product_entity'
        );

        $progressBar = $this->createProgressBar(count($productBatches));

        foreach ($productBatches as $productBatch) {
            $stockData = [
                'cataloginventory_stock_status' => [],
                'cataloginventory_stock_item' => [],
            ];

            $entityIdFrom = $productBatch['id_from'];
            $entityIdTo = $productBatch['id_to'];

            $productEntities = $this->connection->getConnection()
                ->select()->from(
                    $this->connection->getTableName('catalog_product_entity'),
                    ['entity_id', 'sku', 'type_id']
                )
                ->where('entity_id >= ?', $entityIdFrom)
                ->where('entity_id <= ?', $entityIdTo);

            foreach ($this->connection->getConnection()->fetchAll($productEntities) as $productEntity) {
                $entityId = $productEntity['entity_id'];
                $isCompositeType = $this->isProductInCompositeTypes($productEntity['type_id']);
                $stockData['cataloginventory_stock_status'][] = [
                    'product_id' => $entityId,
                    'website_id' => 0,
                    'stock_id' => 1,
                    'qty' => $isCompositeType ? 0 : 100,
                    'stock_status' => 1
                ];

                $stockData['cataloginventory_stock_item'][] = [
                    'product_id' => $entityId,
                    'stock_id' => 1,
                    'qty' => $isCompositeType ? 0 : 100,
                    'min_qty' => $isCompositeType ? 1 : 0,
                    'use_config_min_qty' => 1,
                    'is_qty_decimal' => 0,
                    'backorders' => 0,
                    'use_config_backorders' => 1,
                    'min_sale_qty' => 1,
                    'use_config_min_sale_qty' => 1,
                    'max_sale_qty' => 10000,
                    'use_config_max_sale_qty' => 1,
                    'is_in_stock' => 1,
                    'low_stock_date' => null,
                    'notify_stock_qty' => $isCompositeType ? null : 1,
                    'use_config_notify_stock_qty' => 1,
                    'manage_stock' => 0,
                    'use_config_manage_stock' => 1,
                    'stock_status_changed_auto' => 0,
                    'use_config_qty_increments' => 1,
                    'qty_increments' => 1.000,
                    'use_config_enable_qty_inc' => 1,
                    'enable_qty_increments' => 0,
                    'is_decimal_divided' => 0,
                    'website_id' => 0
                ];

                if (!$isCompositeType) {
                    foreach ($sourceCodes as $sourceCode) {
                        $stockData['inventory_source_item'][] = [
                            'source_code' => $sourceCode,
                            'sku' => $productEntity['sku'],
                            'quantity' => 100,
                            'status' => 1
                        ];
                    }
                }
            }

            foreach ($stockData as $tableName => $tableData) {
                foreach (array_chunk($tableData, 2500) as $dataBatch) {
                    $prepareStatement = $insert->buildInsertQuery(
                        $tableName,
                        array_keys($dataBatch[0]),
                        count($dataBatch)
                    );

                    $this->connection->execute($prepareStatement, InsertMultipleOnDuplicate::flatten($dataBatch));
                }
            }

            $progressBar?->advance();
        }

        $this->finishProgressBar($progressBar);

        return $this;
    }

    /**
     * Get all source codes from inventory_source
     *
     * @return string[]
     */
    private function getSourceCodes(): array
    {
        $query = $this->connection->getConnection()->select()
            ->from($this->connection->getTableName('inventory_source'), ['source_code']);

        return $this->connection->getConnection()->fetchCol($query);
    }
}
