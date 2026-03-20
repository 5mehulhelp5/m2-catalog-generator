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

class AssignToCategories extends AbstractTask implements TaskInterface
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
        $categoryIds = $this->fetchCategoryIds();
        $insert = new InsertMultipleOnDuplicate();
        $progressBar = $this->createProgressBar(count($productBatches));

        foreach ($productBatches as $productBatch) {
            $productCategoryRelation = [];
            $entityIdFrom = $productBatch['id_from'];
            $entityIdTo = $productBatch['id_to'];

            for ($i = $entityIdFrom; $i <= $entityIdTo; $i++) {
                $position = 0;
                $maxCategories = max(2, (int) (count($categoryIds) / 20));
                $randomKeys = array_rand($categoryIds, rand(1, min($maxCategories, count($categoryIds))));
                $randomKeys = is_array($randomKeys) ? $randomKeys : [$randomKeys];

                foreach ($randomKeys as $randomKey) {
                    $productCategoryRelation[] = [
                        'category_id' => $categoryIds[$randomKey],
                        'product_id' => $i,
                        'position' => $position++
                    ];
                }
            }

            foreach (array_chunk($productCategoryRelation, 2500) as $dataBatch) {
                $prepareStatement = $insert->buildInsertQuery(
                    'catalog_category_product',
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
     * Fetch All category Ids
     *
     * @return string[]
     */
    private function fetchCategoryIds(): array
    {
        $query = $this->connection->getConnection()->select()
            ->from($this->connection->getTableName('catalog_category_entity'), ['entity_id'])
            ->where('entity_id > 1');

        return $this->connection->getConnection()->fetchCol($query);
    }
}
