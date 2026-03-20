<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Data\Generators;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Qoliber\CatalogGenerator\Api\Config\CatalogConfigReaderInterface;
use Qoliber\CatalogGenerator\Api\EntityGeneratorInterface;
use Qoliber\CatalogGenerator\Sql\InsertMultipleOnDuplicate;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class ProductGenerator extends AbstractGenerator implements EntityGeneratorInterface
{
    /** @var string */
    private const ENTITY_TABLE = 'catalog_product_entity';

    /** @var int */
    private const BATCH_SIZE = 5000;

    /** @var \Symfony\Component\Console\Output\OutputInterface|null */
    private ?OutputInterface $output = null;

    /** @var int */
    private int $productId = 0;

    /**
     * Set Output
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    /**
     * Get Entity Table
     *
     * @return string
     */
    public function getEntityTable(): string
    {
        return self::ENTITY_TABLE;
    }

    /**
     * Generate Entities. return entity array
     *
     * @param int|string $count
     * @param string $entityType
     * @param mixed[] $entityConfig
     * @return mixed[][]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function generateEntities(int|string $count, string $entityType, array $entityConfig = []): array
    {
        $this->productId = 0;
        $connection = $this->resourceConnection->getConnection();

        foreach ($this->getProductTypes() as $productType => $productTypeConfig) {
            $productTypeGenerator = $this->getDataGenerator(sprintf('product/%s', $productType));
            $typeCount = (int) $productTypeConfig['count'];
            $buffer = [];

            $this->output?->writeln(sprintf(
                '<info>     ↳ Type: <fg=yellow>%s</> (%s products)</info>',
                $productType,
                number_format($typeCount)
            ));

            $progressBar = null;
            if ($this->output) {
                $progressBar = new ProgressBar($this->output, $typeCount);
                $progressBar->setFormat('       %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% / ~%estimated:-6s% %memory:6s%');
                $progressBar->start();
            }

            for ($i = 0; $i < $typeCount; $i++) {
                $this->productId++;
                $hasOptions = $productTypeGenerator !== null;

                $this->addToBuffer($buffer, $this->getEntityTable(), $this->prepareEntityData(
                    $this->productId,
                    $productType,
                    $hasOptions,
                    $hasOptions
                ));

                $attributeData = $this->populateAttributes($entityConfig, $this->productId);
                foreach ($attributeData as $table => $rows) {
                    foreach ($rows as $row) {
                        $this->addToBuffer($buffer, $table, $row);
                    }
                }

                if ($productTypeGenerator) {
                    $childProducts = $productTypeGenerator->getChildProductVariations(
                        $this->productId,
                        $this->configReader->getConfig('prefix'),
                        $productTypeConfig['options']
                    );

                    foreach ($childProducts as $childProduct) {
                        $this->addToBuffer(
                            $buffer,
                            $this->getEntityTable(),
                            $this->prepareEntityData($childProduct['entity_id'], 'simple')
                        );

                        $childEntityConfig = $entityConfig;
                        $childEntityConfig['attributes'] = [
                            ...$entityConfig['attributes'],
                            ...$childProduct['attributes']
                        ];

                        $childAttrData = $this->populateAttributes($childEntityConfig, $childProduct['entity_id']);
                        foreach ($childAttrData as $table => $rows) {
                            foreach ($rows as $row) {
                                $this->addToBuffer($buffer, $table, $row);
                            }
                        }
                    }

                    $this->productId += count($childProducts);
                }

                if ($this->getBufferSize($buffer) >= self::BATCH_SIZE) {
                    $this->flushBuffer($buffer);
                }

                $progressBar?->advance();
            }

            $this->flushBuffer($buffer);
            $progressBar?->finish();
            $this->output?->writeln('');
        }

        return [];
    }

    /**
     * Populate / hydrate attributes
     *
     * @param mixed[] $entityConfig
     * @param int $entityId
     * @return mixed[]
     */
    public function populateAttributes(array $entityConfig, int $entityId): array
    {
        $dataPopulator = $this->getDataPopulator('product/attributes');
        $attributeEntityData = [];

        if ($dataPopulator) {
            foreach ($entityConfig['attributes'] as $attributeCode => $attributeValue) {
                $attributeData = $dataPopulator->getAttributeData(
                    (string) $this->getEntityTypeId('catalog_product'),
                    $attributeCode
                );

                if (empty($attributeData)) {
                    continue;
                }

                $attributeTable = $this->resourceConnection->getConnection()->getTableName(
                    sprintf('%s_%s', $this->getEntityTable(), $attributeData['backend_type'])
                );
                $attributeId = $attributeData['attribute_id'];
                $attributeEntityData[$attributeTable][] = [
                    'attribute_id' => $attributeId,
                    'store_id' => 0,
                    'entity_id' => $entityId,
                    'value' => $this->getAttributeValue($attributeValue)
                ];
            }
        }

        return $attributeEntityData;
    }

    /**
     * Get Product Types
     *
     * @return mixed[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getProductTypes(): array
    {
        if ($productTypes = $this->configReader->getConfig('entities')['product']['types'] ?? null) {
            return $productTypes;
        } else {
            throw new LocalizedException(__('Invalid configuration file'));
        }
    }

    /**
     * Prepare Entity
     *
     * @param int $i
     * @param string $entityType
     * @param bool $hasOptions
     * @param bool $requiredOptions
     * @return array<string, string|int|bool>
     */
    private function prepareEntityData(
        int $i,
        string $entityType,
        bool $hasOptions = false,
        bool $requiredOptions = false
    ): array {
        return [
            'entity_id' => $i,
            'attribute_set_id' => $this->getDefaultAttributeSetId('catalog_product'),
            'type_id' => $entityType,
            'sku' => sprintf(
                '%s_%d',
                $this->configReader->getConfig('prefix'),
                $i
            ),
            'has_options' => $hasOptions,
            'required_options' => $requiredOptions,
        ];
    }

    /**
     * Add a row to the buffer
     *
     * @param mixed[][] $buffer
     * @param string $table
     * @param mixed[] $row
     * @return void
     */
    private function addToBuffer(array &$buffer, string $table, array $row): void
    {
        $buffer[$table][] = $row;
    }

    /**
     * Get buffer row count (entity table only for sizing)
     *
     * @param mixed[][] $buffer
     * @return int
     */
    private function getBufferSize(array $buffer): int
    {
        return count($buffer[$this->getEntityTable()] ?? []);
    }

    /**
     * Flush buffer to database and clear
     *
     * @param mixed[][] $buffer
     * @return void
     */
    private function flushBuffer(array &$buffer): void
    {
        foreach ($buffer as $tableName => $tableData) {
            if (empty($tableData)) {
                continue;
            }

            foreach (array_chunk($tableData, self::BATCH_SIZE) as $chunk) {
                $query = new InsertMultipleOnDuplicate();
                $statement = $query->buildInsertQuery(
                    $this->resourceConnection->getConnection()->getTableName($tableName),
                    array_keys($chunk[0]),
                    count($chunk)
                );

                $this->resourceConnection->getConnection()
                    ->prepare($statement)
                    ->execute(InsertMultipleOnDuplicate::flatten($chunk));
            }
        }

        $buffer = [];
    }
}
