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

class AssignCustomAttributesTask extends AbstractProductTask implements TaskInterface
{
    /**
     * Run Task
     *
     * @return \Qoliber\CatalogGenerator\Api\Task\TaskInterface
     * @throws \Exception
     */
    public function runTask(): TaskInterface
    {
        $conn = $this->connection->getConnection();
        $attributes = $this->getCustomAttributes();

        if (empty($attributes)) {
            return $this;
        }

        $productBatches = $this->connection->getEntityBatches('entity_id', 'catalog_product_entity');
        $progressBar = $this->createProgressBar(count($productBatches));

        foreach ($productBatches as $productBatch) {
            $intData = [];
            $varcharData = [];

            $productEntities = $conn->fetchAll(
                $conn->select()->from(
                    $this->connection->getTableName('catalog_product_entity'),
                    ['entity_id', 'type_id']
                )
                    ->where('entity_id >= ?', $productBatch['id_from'])
                    ->where('entity_id <= ?', $productBatch['id_to'])
            );

            foreach ($productEntities as $product) {
                foreach ($attributes as $attribute) {
                    $percent = (int) ($attribute['assignment_percent'] ?? 100);

                    if (rand(1, 100) > $percent) {
                        continue;
                    }

                    $options = $attribute['options'];

                    if (empty($options)) {
                        continue;
                    }

                    if ($attribute['frontend_input'] === 'select') {
                        $randomOptionId = $options[array_rand($options)];
                        $intData[] = [
                            'attribute_id' => (int) $attribute['attribute_id'],
                            'store_id' => 0,
                            'entity_id' => (int) $product['entity_id'],
                            'value' => (int) $randomOptionId,
                        ];
                    } elseif ($attribute['frontend_input'] === 'multiselect') {
                        $count = rand(1, count($options));
                        $randomKeys = array_rand($options, $count);
                        $selectedOptions = is_array($randomKeys)
                            ? array_map(fn($k) => $options[$k], $randomKeys)
                            : [$options[$randomKeys]];
                        $varcharData[] = [
                            'attribute_id' => (int) $attribute['attribute_id'],
                            'store_id' => 0,
                            'entity_id' => (int) $product['entity_id'],
                            'value' => implode(',', $selectedOptions),
                        ];
                    }
                }
            }

            $this->batchInsert('catalog_product_entity_int', $intData);
            $this->batchInsert('catalog_product_entity_varchar', $varcharData);
            $progressBar?->advance();
        }

        $this->finishProgressBar($progressBar);

        return $this;
    }

    /**
     * Get custom attributes with their options
     *
     * @return mixed[][]
     */
    private function getCustomAttributes(): array
    {
        $conn = $this->connection->getConnection();
        $entityTypeId = $this->getEntityTypeId('catalog_product');

        $dropdowns = $conn->fetchAll(
            $conn->select()->from(
                $this->connection->getTableName('eav_attribute'),
                ['attribute_id', 'attribute_code', 'frontend_input']
            )
                ->where('entity_type_id = ?', $entityTypeId)
                ->where('is_user_defined = 1')
                ->where('attribute_code REGEXP ?', '^dropdown_[0-9]+$')
        );

        $multiselects = $conn->fetchAll(
            $conn->select()->from(
                $this->connection->getTableName('eav_attribute'),
                ['attribute_id', 'attribute_code', 'frontend_input']
            )
                ->where('entity_type_id = ?', $entityTypeId)
                ->where('is_user_defined = 1')
                ->where('attribute_code REGEXP ?', '^multiselect_[0-9]+$')
        );

        $attributes = array_merge($dropdowns, $multiselects);
        $result = [];

        foreach ($attributes as $attribute) {
            $options = $conn->fetchCol(
                $conn->select()->from(
                    $this->connection->getTableName('eav_attribute_option'),
                    ['option_id']
                )->where('attribute_id = ?', $attribute['attribute_id'])
            );

            if (empty($options)) {
                continue;
            }

            $assignmentPercent = 60;
            if (str_starts_with($attribute['attribute_code'], 'multiselect')) {
                $assignmentPercent = 40;
            }

            $result[] = [
                'attribute_id' => $attribute['attribute_id'],
                'attribute_code' => $attribute['attribute_code'],
                'frontend_input' => $attribute['frontend_input'],
                'options' => $options,
                'assignment_percent' => $assignmentPercent,
            ];
        }

        return $result;
    }

    /**
     * Batch insert data into a table
     *
     * @param string $tableName
     * @param mixed[][] $data
     * @return void
     * @throws \Exception
     */
    private function batchInsert(string $tableName, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $insert = (new InsertMultipleOnDuplicate())->onDuplicate(['value']);

        foreach (array_chunk($data, 2500) as $dataBatch) {
            $prepareStatement = $insert->buildInsertQuery(
                $tableName,
                array_keys($dataBatch[0]),
                count($dataBatch)
            );

            $this->connection->execute($prepareStatement, InsertMultipleOnDuplicate::flatten($dataBatch));
        }
    }
}
