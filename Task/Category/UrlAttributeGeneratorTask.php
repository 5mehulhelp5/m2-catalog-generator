<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Task\Category;

use Qoliber\CatalogGenerator\Api\Task\TaskInterface;
use Qoliber\CatalogGenerator\Sql\InsertMultipleOnDuplicate;
use Qoliber\CatalogGenerator\Task\AbstractUrlGenerator;

class UrlAttributeGeneratorTask extends AbstractUrlGenerator implements TaskInterface
{
    public const CHUNK_SIZE = 1000;

    /**
     * Run Task
     *
     * @return \Qoliber\CatalogGenerator\Api\Task\TaskInterface
     * @throws \Exception
     */
    public function runTask(): TaskInterface
    {
        $categoryUrlPaths = [];
        $nameAttributeId = $this->getAttributeId($this->getEntityTypeId('catalog_category'), 'name');
        $urlKeyAttributeId = $this->getAttributeId($this->getEntityTypeId('catalog_category'), 'url_key');
        $urlPathAttributed = $this->getAttributeId($this->getEntityTypeId('catalog_category'), 'url_path');
        $fastQuery = new InsertMultipleOnDuplicate();
        $categoryEntityBatches = $this->connection->getEntityBatches('entity_id', 'catalog_category_entity');

        $progressBar = $this->createProgressBar(count($categoryEntityBatches));

        foreach ($categoryEntityBatches as $batch) {
            $dataToInsert = [];
            $entityIdFrom = $batch['id_from'];
            $entityTo = $batch['id_to'];

            $query = $this->connection->getConnection()->select()
                ->from($this->connection->getTableName('catalog_category_entity'), ['entity_id', 'path'])
                ->joinLeft(
                    ['name' => 'catalog_category_entity_varchar'],
                    sprintf(
                        'name.entity_id = catalog_category_entity.entity_id and name.attribute_id = "%s"',
                        $nameAttributeId
                    ),
                    ['value']
                )
                ->where('catalog_category_entity.entity_id > 1')
                ->where('catalog_category_entity.entity_id >= ?', $entityIdFrom)
                ->where('catalog_category_entity.entity_id <= ?', $entityTo)
                ->order('level asc');

            foreach ($this->connection->getConnection()->fetchAll($query) as $categoryData) {
                $urlKey = $this->getSeoValue($categoryData['value']);
                $categoryUrlPaths[$categoryData['entity_id']] = $urlKey;
                $urlPath = $this->buildUrlPath($categoryData['path'], $categoryUrlPaths);

                $dataToInsert[] = [
                    'attribute_id' => $urlKeyAttributeId,
                    'store_id' => 0,
                    'entity_id' => $categoryData['entity_id'],
                    'value' => $urlKey
                ];

                $dataToInsert[] = [
                    'attribute_id' => $urlPathAttributed,
                    'store_id' => 0,
                    'entity_id' => $categoryData['entity_id'],
                    'value' => $urlPath
                ];
            }

            foreach (array_chunk($dataToInsert, self::CHUNK_SIZE) as $dataBatch) {
                $statement = $fastQuery->buildInsertQuery(
                    $this->connection->getTableName('catalog_category_entity_varchar'),
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

    /**
     * Build Url Path base on category path (1/2/3 -> category1/category2/category3)
     *
     * @param string $categoryPath
     * @param string[] $categoryUrlPaths
     * @return string
     */
    private function buildUrlPath(string $categoryPath, array $categoryUrlPaths): string
    {
        $path = [];
        $categoryUrlPathIds = explode('/', $categoryPath);
        $categoryUrlPathIds = array_slice($categoryUrlPathIds, 1);

        foreach ($categoryUrlPathIds as $categoryUrlPathId) {
            $path[] = $categoryUrlPaths[$categoryUrlPathId];
        }

        return implode('/', $path);
    }
}
