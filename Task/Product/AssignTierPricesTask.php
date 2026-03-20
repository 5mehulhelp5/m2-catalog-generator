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

use Magento\Catalog\Model\ProductTypes\ConfigInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Qoliber\CatalogGenerator\Api\Config\CatalogConfigReaderInterface;
use Qoliber\CatalogGenerator\Api\Task\TaskInterface;
use Qoliber\CatalogGenerator\Sql\Connection;
use Qoliber\CatalogGenerator\Sql\InsertMultipleOnDuplicate;

class AssignTierPricesTask extends AbstractProductTask implements TaskInterface
{
    /** @var int[] */
    private const QTY_TIERS = [2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000];

    /**
     * @param \Qoliber\CatalogGenerator\Api\Config\CatalogConfigReaderInterface $configReader
     * @param \Magento\Catalog\Model\ProductTypes\ConfigInterface $productConfigInterface
     * @param \Magento\Framework\Filesystem\Io\File $ioFile
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Filesystem\Driver\File $file
     * @param \Qoliber\CatalogGenerator\Sql\Connection $connection
     * @param mixed[] $attributeData
     * @param string[] $compositeProductTypes
     */
    public function __construct(
        private readonly CatalogConfigReaderInterface $configReader,
        protected ConfigInterface $productConfigInterface,
        protected IoFile $ioFile,
        protected Filesystem $filesystem,
        protected File $file,
        protected Connection $connection,
        protected array $attributeData = [],
        private array $compositeProductTypes = [],
    ) {
        parent::__construct(
            $this->productConfigInterface,
            $this->ioFile,
            $this->filesystem,
            $this->file,
            $this->connection,
            $this->attributeData,
            $this->compositeProductTypes,
        );
    }

    /**
     * Run Task
     *
     * @return \Qoliber\CatalogGenerator\Api\Task\TaskInterface
     * @throws \Exception
     */
    public function runTask(): TaskInterface
    {
        $productConfig = $this->configReader->getConfig('entities')['product'] ?? [];
        $config = $productConfig['tier_prices'] ?? [];

        if (empty($config)) {
            return $this;
        }

        $levels = (int) ($config['levels'] ?? 0);

        if ($levels <= 0) {
            return $this;
        }

        $levels = min($levels, count(self::QTY_TIERS));
        $websiteIds = $this->getWebsiteIds();
        $customerGroupIds = $this->getCustomerGroupIds();
        $insert = new InsertMultipleOnDuplicate();
        $productBatches = $this->connection->getEntityBatches('entity_id', 'catalog_product_entity');
        $progressBar = $this->createProgressBar(count($productBatches));

        foreach ($productBatches as $batch) {
            $tierData = [];
            $products = $this->connection->getConnection()->fetchAll(
                $this->connection->getConnection()->select()
                    ->from($this->connection->getTableName('catalog_product_entity'), ['entity_id', 'type_id'])
                    ->where('entity_id >= ?', $batch['id_from'])
                    ->where('entity_id <= ?', $batch['id_to'])
                    ->where('type_id = ?', 'simple')
            );

            foreach ($products as $product) {
                for ($i = 0; $i < $levels; $i++) {
                    $qty = self::QTY_TIERS[$i];
                    $percentage = $i + 1;

                    foreach ($websiteIds as $websiteId) {
                        $tierData[] = [
                            'entity_id' => (int) $product['entity_id'],
                            'all_groups' => 1,
                            'customer_group_id' => 0,
                            'qty' => (float) $qty,
                            'value' => 0.00,
                            'website_id' => (int) $websiteId,
                            'percentage_value' => (float) $percentage,
                        ];
                    }
                }
            }

            if (!empty($tierData)) {
                foreach (array_chunk($tierData, 2500) as $dataBatch) {
                    $stmt = $insert->buildInsertQuery(
                        'catalog_product_entity_tier_price',
                        array_keys($dataBatch[0]),
                        count($dataBatch)
                    );

                    $this->connection->execute($stmt, InsertMultipleOnDuplicate::flatten($dataBatch));
                }
            }

            $progressBar?->advance();
        }

        $this->finishProgressBar($progressBar);

        return $this;
    }

    /**
     * Get all website IDs (including 0 for all websites)
     *
     * @return int[]
     */
    private function getWebsiteIds(): array
    {
        return [0];
    }

    /**
     * Get all customer group IDs
     *
     * @return string[]
     */
    private function getCustomerGroupIds(): array
    {
        return $this->connection->getConnection()->fetchCol(
            $this->connection->getConnection()->select()
                ->from($this->connection->getTableName('customer_group'), ['customer_group_id'])
        );
    }
}
