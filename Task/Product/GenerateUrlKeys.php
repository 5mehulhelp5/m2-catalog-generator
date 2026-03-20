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
use Qoliber\CatalogGenerator\Task\AbstractUrlGenerator;

class GenerateUrlKeys extends AbstractUrlGenerator implements TaskInterface
{
    /**
     * Run Task
     *
     * @return \Qoliber\CatalogGenerator\Api\Task\TaskInterface
     * @throws \Exception
     */
    public function runTask(): TaskInterface
    {
        $nameAttributeId = $this->getAttributeId($this->getEntityTypeId('catalog_product'), 'name');
        $urlKeyAttributeId = $this->getAttributeId($this->getEntityTypeId('catalog_product'), 'url_key');
        $fastQuery = new InsertMultipleOnDuplicate();

        $productEntityBatches = $this->connection->getEntityBatches(
            'entity_id',
            'catalog_product_entity'
        );

        $progressBar = $this->createProgressBar(count($productEntityBatches));

        foreach ($productEntityBatches as $batch) {
            $dataToInsert = [];
            $entityIdFrom = $batch['id_from'];
            $entityTo = $batch['id_to'];

            $query = $this->connection->getConnection()->select()
                ->from($this->connection->getTableName('catalog_product_entity'), ['entity_id'])
                ->joinLeft(
                    ['name' => 'catalog_product_entity_varchar'],
                    sprintf(
                        'name.entity_id = catalog_product_entity.entity_id and name.attribute_id = "%s"',
                        $nameAttributeId
                    ),
                    ['value']
                )
                ->where('catalog_product_entity.entity_id >= ?', $entityIdFrom)
                ->where('catalog_product_entity.entity_id <= ?', $entityTo);

            foreach ($this->connection->getConnection()->fetchAll($query) as $categoryData) {
                $urlKey = $this->getSeoValue($categoryData['value']);
                $dataToInsert[] = [
                    'attribute_id' => $urlKeyAttributeId,
                    'store_id' => 0,
                    'entity_id' => $categoryData['entity_id'],
                    'value' => $urlKey
                ];
            }

            foreach (array_chunk($dataToInsert, 2500) as $dataBatch) {
                $statement = $fastQuery->buildInsertQuery(
                    $this->connection->getTableName('catalog_product_entity_varchar'),
                    array_keys($dataBatch[0]),
                    count($dataBatch)
                );

                $this->connection->execute($statement, InsertMultipleOnDuplicate::flatten($dataBatch));
            }

            $progressBar?->advance();
        }

        $this->finishProgressBar($progressBar);

        return $this;
    }
}
