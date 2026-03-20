<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Service;

use Magento\Framework\Event\ManagerInterface;
use Qoliber\CatalogGenerator\Api\EntityGeneratorInterface;
use Qoliber\CatalogGenerator\Api\Service\CatalogGenerationServiceInterface;
use Qoliber\CatalogGenerator\Api\Config\CatalogConfigReaderInterface;
use Qoliber\CatalogGenerator\Api\Service\CleanUpServiceInterface;
use Qoliber\CatalogGenerator\Sql\Connection;
use Qoliber\CatalogGenerator\Sql\InsertMultipleOnDuplicate;
use Symfony\Component\Console\Output\OutputInterface;

class CatalogGenerationService implements CatalogGenerationServiceInterface
{
    /**
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Qoliber\CatalogGenerator\Sql\Connection $connection
     * @param \Qoliber\CatalogGenerator\Api\Config\CatalogConfigReaderInterface $configReader
     * @param \Qoliber\CatalogGenerator\Api\Service\CleanUpServiceInterface $cleanUpService
     * @param \Qoliber\CatalogGenerator\Api\EntityGeneratorInterface[] $entityGenerators
     * @param \Symfony\Component\Console\Output\OutputInterface|null $output
     */
    public function __construct(
        protected ManagerInterface $eventManager,
        protected Connection $connection,
        protected CatalogConfigReaderInterface $configReader,
        protected CleanUpServiceInterface $cleanUpService,
        protected array $entityGenerators = [],
        protected ?OutputInterface $output = null
    ) {
    }

    /**
     * Set Output
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return \Qoliber\CatalogGenerator\Api\Service\CatalogGenerationServiceInterface
     */
    public function setOutput(OutputInterface $output): CatalogGenerationServiceInterface
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Generate Catalog
     *
     * @param string $configFile
     * @return bool
     * @throws \Exception
     */
    public function generate(string $configFile): bool
    {
        $this->output?->writeln('<info>Catalog Generation started...</info>');
        $config = $this->configReader->initializeConfig($configFile);

        $this->output?->writeln('<comment> --> cleaning old data...</comment>');
        $this->cleanUpService->cleanUpData();

        $this->connection->getConnection()->query('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($config['entities'] as $entity => $entityConfig) {
                $entityGenerator = $this->entityGenerators[$entity] ?? null;

                if ($entityGenerator) {
                    if (method_exists($entityGenerator, 'setOutput') && $this->output) {
                        $entityGenerator->setOutput($this->output);
                    }

                    $this->output?->writeln(sprintf('<info> --> Generating <fg=yellow>%s</> data</info>', $entity));
                    $data = $entityGenerator->generateEntities($entityConfig['count'] ?? 0, '', $entityConfig);

                    if (!empty($data)) {
                        $this->output?->writeln(sprintf(
                            '<info>   ↳ Inserting <fg=white>%s</> data</info>',
                            $entity
                        ));
                        $this->insertData($data);
                    }

                    $this->output?->writeln('<info>   ↳ Running tasks:</info>');
                    foreach ($entityConfig['tasks'] ?? [] as $taskName) {
                        if ($task = $entityGenerator->getTask($taskName)) {
                            if (method_exists($task, 'setOutput') && $this->output) {
                                $task->setOutput($this->output);
                            }
                            $this->output?->writeln(sprintf(
                                '<info>     ↳ Running <fg=cyan>%s</> task</info>',
                                $taskName
                            ));
                            $task->runTask();
                        }
                    }
                }
            }
        } finally {
            $this->connection->getConnection()->query('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->output?->writeln('');
        $this->output?->writeln('');

        return true;
    }

    /**
     * Get Entity Generator
     *
     * @param string $type
     * @return \Qoliber\CatalogGenerator\Api\EntityGeneratorInterface|null
     */
    public function getEntityGenerator(string $type): ?EntityGeneratorInterface
    {
        return $this->entityGenerators[$type] ?? null;
    }

    /**
     * Insert data into database in chunks
     *
     * @param mixed[][] $data
     * @return void
     */
    private function insertData(array $data): void
    {
        foreach ($data as $tableName => $tableData) {
            foreach (array_chunk($tableData, 5000) as $chunk) {
                $query = new InsertMultipleOnDuplicate();
                $statement = $query->buildInsertQuery(
                    $this->connection->getTableName($tableName),
                    array_keys($chunk[0]),
                    count($chunk)
                );

                $this->connection->execute(
                    $statement,
                    InsertMultipleOnDuplicate::flatten($chunk)
                );
            }
        }
    }
}
