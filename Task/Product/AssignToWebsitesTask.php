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

class AssignToWebsitesTask extends AbstractTask implements TaskInterface
{
    /**
     * Run Task
     *
     * @return \Qoliber\CatalogGenerator\Api\Task\TaskInterface
     * @throws \Exception
     */
    public function runTask(): TaskInterface
    {
        $productBatches = $this->connection->getEntityBatches('entity_id', 'catalog_product_entity');
        $websiteIds = $this->getWebsiteIds();
        $insert = new InsertMultipleOnDuplicate();
        $progressBar = $this->createProgressBar(count($productBatches));

        foreach ($productBatches as $productBatch) {
            $productWebsiteRelation = [];
            $entityIdFrom = $productBatch['id_from'];
            $entityIdTo = $productBatch['id_to'];

            for ($productId = $entityIdFrom; $productId <= $entityIdTo; $productId++) {
                foreach ($websiteIds as $websiteId) {
                    $productWebsiteRelation[] = [
                        'product_id' => $productId,
                        'website_id' => $websiteId
                    ];
                }
            }

            foreach (array_chunk($productWebsiteRelation, 2500) as $dataBatch) {
                $prepareStatement = $insert->buildInsertQuery(
                    'catalog_product_website',
                    array_keys($dataBatch[0]),
                    count($dataBatch)
                );

                $this->connection->execute($prepareStatement, InsertMultipleOnDuplicate::flatten($dataBatch));
            }

            $progressBar?->advance();
        }

        $this->finishProgressBar($progressBar);

        return $this;
    }

    /**
     * Get Website Ids
     *
     * @return string[]
     */
    private function getWebsiteIds(): array
    {
        $query = $this->connection->getConnection()->select()
            ->from($this->connection->getTableName('store_website'), ['website_id'])
            ->where('website_id > 0');

        return $this->connection->getConnection()->fetchCol($query);
    }
}
