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
use Qoliber\CatalogGenerator\Api\Config\CatalogConfigReaderInterface;
use Qoliber\CatalogGenerator\Api\DataGeneratorInterface;
use Qoliber\CatalogGenerator\Api\DataPopulatorInterface;
use Qoliber\CatalogGenerator\Api\EntityGeneratorInterface;
use Qoliber\CatalogGenerator\Api\Resolver\ResolverInterface;
use Qoliber\CatalogGenerator\Api\Task\TaskInterface;

abstract class AbstractGenerator implements EntityGeneratorInterface
{
    /**
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Qoliber\CatalogGenerator\Api\Config\CatalogConfigReaderInterface $configReader
     * @param \Qoliber\CatalogGenerator\Api\DataGeneratorInterface[] $dataGenerators
     * @param \Qoliber\CatalogGenerator\Api\DataPopulatorInterface[] $dataPopulators
     * @param \Qoliber\CatalogGenerator\Api\Resolver\ResolverInterface[] $dataResolvers
     * @param \Qoliber\CatalogGenerator\Api\Task\TaskInterface[] $tasks
     */
    public function __construct(
        protected ResourceConnection $resourceConnection,
        protected CatalogConfigReaderInterface $configReader,
        protected array $dataGenerators = [],
        protected array $dataPopulators = [],
        protected array $dataResolvers = [],
        protected array $tasks = []
    ) {
    }

    /**
     * Generate Child Entities
     *
     * @param int $parentId
     * @param int $childCount
     * @param mixed[] $entityConfig
     * @return mixed[]
     */
    public function generateChildEntities(int $parentId, int $childCount, array $entityConfig = []): array
    {
        return [];
    }

    /**
     * Get Data Generator
     *
     * @param string $entityType
     * @return \Qoliber\CatalogGenerator\Api\DataGeneratorInterface|null
     */
    public function getDataGenerator(string $entityType): ?DataGeneratorInterface
    {
        return $this->dataGenerators[$entityType] ?? null;
    }

    /**
     * Get Data Populator (Hydrators?)
     *
     * @param string $entityType
     * @return \Qoliber\CatalogGenerator\Api\DataPopulatorInterface|null
     */
    public function getDataPopulator(string $entityType): ?DataPopulatorInterface
    {
        return $this->dataPopulators[$entityType] ?? null;
    }

    /**
     * Get Data Resolver
     *
     * @param string $resolverName
     * @return \Qoliber\CatalogGenerator\Api\Resolver\ResolverInterface|null
     */
    public function getDataResolver(string $resolverName): ?ResolverInterface
    {
        return $this->dataResolvers[$resolverName] ?? null;
    }

    /**
     * Generate Attributes
     *
     * @param mixed[] $entityArray
     * @param mixed[] $entityConfig
     * @return mixed[]
     */
    public function generateAttributes(array $entityArray, array $entityConfig): array
    {
        foreach ($entityArray[$this->getEntityTable()] as $attributeData) {
            $attributeArray = $this->populateAttributes($entityConfig, $attributeData['entity_id']);
            $this->populateEntityTableArray($attributeArray, $entityArray);
        }

        return $entityArray;
    }

    /**
     * Get Attribute Value (from yml file)
     *
     * @param mixed $attributeValue
     * @return mixed
     */
    public function getAttributeValue(mixed $attributeValue): mixed
    {
        if (!is_float($attributeValue) && !is_int($attributeValue) && str_contains($attributeValue, 'resolver')) {
            return $this->getDataResolver('name')?->resolveData();
        }

        return $attributeValue;
    }

    /**
     * Get Entity Relates Tasks
     *
     * @param string $taskName
     * @return \Qoliber\CatalogGenerator\Api\Task\TaskInterface|null
     */
    public function getTask(string $taskName): ?TaskInterface
    {
        return $this->tasks[$taskName] ?? null;
    }

    /**
     * Populate Entity Table Array
     *
     * @param mixed[] $attributeArray
     * @param mixed[] $entityArray
     * @return mixed[]
     */
    public function populateEntityTableArray(array $attributeArray, array &$entityArray): array
    {
        foreach ($attributeArray as $backendTable => $backendData) {
            if (!isset($entityArray[$backendTable])) {
                $entityArray[$backendTable] = [];
            }

            foreach ($backendData as $attributeData) {
                $entityArray[$backendTable][] = $attributeData;
            }
        }

        return [];
    }
}
