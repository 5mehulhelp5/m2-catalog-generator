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
use Qoliber\CatalogGenerator\Task\AbstractUrlGenerator;

class UrlGeneratorTask extends AbstractUrlGenerator implements TaskInterface
{
    /**
     * Run Task
     *
     * @return \Qoliber\CatalogGenerator\Api\Task\TaskInterface
     * @throws \Exception
     */
    public function runTask(): TaskInterface
    {
        $urlRewriteArray = [];
        $entityIds = $this->getCategoryIds();
        $storeIds = $this->getStoreIds();

        foreach ($entityIds as $categoryId) {
            $categoryPath = $this->getCategoryPath((int) $categoryId);
            $categoryPathIds = explode('/', $categoryPath);
            $categoryPathIds = array_slice($categoryPathIds, 2);

            foreach ($storeIds as $storeId) {
                $path = [];

                foreach ($categoryPathIds as $categoryPathId) {
                    $path[] = $this->getCategoryUrlPerStore((int)$categoryPathId, (int)$storeId);
                }

                $requestPath = implode('/', $path);
                $targetPath = $this->getTargetPath('category', (int) $categoryId);
                $urlRewriteArray[] = $this->prepareRow(
                    $categoryId,
                    $requestPath,
                    $targetPath,
                    $storeId,
                    'category'
                );
            }
        }

        $this->saveAndUpdateUrls($urlRewriteArray);

        return $this;
    }

    /**
     * Get Category URL per store //TODO - implement multiple SEO values per store - for BETA2.0
     *
     * @param int $categoryId
     * @param int $storeId
     * @return string
     */
    private function getCategoryUrlPerStore(int $categoryId, int $storeId): string
    {
        $query = $this->connection->getConnection()->select()
            ->from(
                $this->connection->getConnection()->getTableName(
                    sprintf('%s_varchar', 'catalog_category_entity')
                ),
                ['value']
            )
            ->where('entity_id = ?', $categoryId)
            ->where('store_id in (?)', [0, $storeId])
            ->where('attribute_id = ?', $this->getUrlKeyAttributeId($this->getEntityTypeId('catalog_category')))
            ->order('store_id desc');

        return $this->connection->getConnection()->fetchOne($query);
    }

    //TODO - fetch paths with IDs to remove this query
    /**
     * Get Category Path
     *
     * @param int $categoryId
     * @return string
     */
    private function getCategoryPath(int $categoryId): string
    {
        $query = $this->connection->getConnection()->select()
            ->from(
                $this->connection->getConnection()->getTableName('catalog_category_entity'),
                ['path']
            )
            ->where('entity_id = ?', $categoryId);

        return $this->connection->getConnection()->fetchOne($query);
    }

    /**
     * Get Category IDs
     *
     * @return string[]
     */
    private function getCategoryIds(): array
    {
        $sql = $this->connection->getConnection()->select()
            ->from($this->connection->getTableName('catalog_category_entity'));

        return $this->connection->getConnection()->fetchCol($sql);
    }
}
