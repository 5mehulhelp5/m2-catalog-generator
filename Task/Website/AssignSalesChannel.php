<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Task\Website;

use Qoliber\CatalogGenerator\Api\Task\TaskInterface;
use Qoliber\CatalogGenerator\Sql\InsertMultipleOnDuplicate;
use Qoliber\CatalogGenerator\Task\AbstractTask;

class AssignSalesChannel extends AbstractTask implements TaskInterface
{
    /**
     * Run Task
     *
     * @return \Qoliber\CatalogGenerator\Api\Task\TaskInterface
     * @throws \Exception
     */
    public function runTask(): TaskInterface
    {
        $dataToInsert = [];
        $websiteCodes = $this->getWebsiteCodes();
        $stockIds = $this->getStockIds();

        foreach ($websiteCodes as $index => $websiteCode) {
            $stockId = $stockIds[$index % count($stockIds)];
            $dataToInsert[] = [
                'type' => 'website',
                'code' => $websiteCode,
                'stock_id' => (int) $stockId,
            ];
        }

        if (empty($dataToInsert)) {
            return $this;
        }

        $insert = new InsertMultipleOnDuplicate();
        $prepareStatement = $insert->buildInsertQuery(
            'inventory_stock_sales_channel',
            array_keys($dataToInsert[0]),
            count($dataToInsert)
        );

        $this->connection->execute($prepareStatement, InsertMultipleOnDuplicate::flatten($dataToInsert));

        return $this;
    }

    /**
     * Get Website Codes
     *
     * @return string[]
     */
    private function getWebsiteCodes(): array
    {
        $websiteCodesSql = $this->connection->getConnection()->select()
            ->from($this->connection->getTableName('store_website'), ['code'])
            ->where('website_id > 0');

        return $this->connection->getConnection()->fetchCol($websiteCodesSql);
    }

    /**
     * Get all stock IDs
     *
     * @return string[]
     */
    private function getStockIds(): array
    {
        $query = $this->connection->getConnection()->select()
            ->from($this->connection->getTableName('inventory_stock'), ['stock_id']);

        return $this->connection->getConnection()->fetchCol($query);
    }
}
