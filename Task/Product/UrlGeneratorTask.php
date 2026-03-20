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
        $productEntityBatches = $this->connection->getEntityBatches('entity_id', 'catalog_product_entity');

        $storeIds = $this->getStoreIds();
        $totalBatches = count($productEntityBatches) * count($storeIds);
        $progressBar = $this->createProgressBar($totalBatches);

        foreach ($storeIds as $storeId) {
            $urlRewriteArray = [];

            foreach ($productEntityBatches as $batch) {
                $ulrKeysData = $this->getProductIdsWithUrlKeys(
                    (int) $batch['id_from'],
                    (int) $batch['id_to'],
                );

                foreach ($ulrKeysData as $productId => $urlKey) {
                    $targetPath = $this->getTargetPath('product', (int) $productId);
                    $urlRewriteArray[] = $this->prepareRow(
                        $productId,
                        $urlKey,
                        $targetPath,
                        $storeId,
                        'product'
                    );
                }

                $progressBar?->advance();
            }
            $this->saveAndUpdateUrls($urlRewriteArray);
        }

        $this->finishProgressBar($progressBar);

        return $this;
    }

    /**
     * Get Product IDs
     *
     * @param int $entityIdFrom
     * @param int $entityIdTo
     * @return string[]
     */
    private function getProductIdsWithUrlKeys(int $entityIdFrom, int $entityIdTo): array
    {
        $urlKeyAttributeId = $this->getAttributeId($this->getEntityTypeId('catalog_product'), 'url_key');
        $sql = $this->connection->getConnection()->select()
            ->from($this->connection->getTableName('catalog_product_entity'), ['entity_id'])
            ->joinLeft(
                ['url' => 'catalog_product_entity_varchar'],
                'url.entity_id = catalog_product_entity.entity_id
                    and url.store_id = 0
                    and url.attribute_id = ' . $urlKeyAttributeId,
                ['value']
            )
            ->where('url.entity_id >= ?', $entityIdFrom)
            ->where('url.entity_id <= ?', $entityIdTo);

        return $this->connection->getConnection()->fetchPairs($sql);
    }
}
