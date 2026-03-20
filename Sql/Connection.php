<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Sql;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;

class Connection
{
    /** @var int */
    private const BATCH_SIZE = 1000;

    /** @var array<string, int> */
    private array $entityTypeIdCache = [];

    /**
     * @param \Magento\Framework\App\ResourceConnection $resource
     */
    public function __construct(
        protected ResourceConnection $resource,
    ) {
    }

    /**
     * Get Entity Type ID by entity type code
     *
     * @param string $entityTypeCode e.g. 'catalog_product', 'catalog_category'
     * @return int
     */
    public function getEntityTypeId(string $entityTypeCode): int
    {
        if (!isset($this->entityTypeIdCache[$entityTypeCode])) {
            $query = $this->getConnection()->select()
                ->from($this->getTableName('eav_entity_type'), ['entity_type_id'])
                ->where('entity_type_code = ?', $entityTypeCode);

            $this->entityTypeIdCache[$entityTypeCode] = (int) $this->getConnection()->fetchOne($query);
        }

        return $this->entityTypeIdCache[$entityTypeCode];
    }

    /**
     * Get Connection
     *
     * @return AdapterInterface|Mysql
     */
    public function getConnection(): AdapterInterface|Mysql
    {
        return $this->resource->getConnection();
    }

    /**
     * Get Table Name
     *
     * @param string $tableName
     * @return string
     */
    public function getTableName(string $tableName): string
    {
        return $this->getConnection()->getTableName($tableName);
    }

    /**
     * Execute Query
     *
     * @param string $preparedStatement
     * @param mixed[] $data
     * @throws \Exception
     */
    public function execute(string $preparedStatement, array $data): void
    {
        // @phpstan-ignore-next-line
        $this->getConnection()->prepare(
            $preparedStatement
        )->execute(
            $data
        );
    }

    /**
     * Execute Query
     *
     * @param string $query
     * @return mixed
     */
    public function executeQuery(string $query): mixed
    {
        return $this->getConnection()->query($query);
    }

    /**
     * Get Entity Batches
     *
     * @param string $autoIncrementField
     * @param string $table
     * @return array
     */
    /**
     * Get Entity Batches
     *
     * @param string $autoIncrementField
     * @param string $table
     * @return string[][]
     */
    public function getEntityBatches(string $autoIncrementField, string $table): array
    {
        return $this->getConnection()->fetchAll(
            sprintf(
                '
            WITH batches AS (
                SELECT
                    CEIL(%1$s / %2$d) AS batch_number,
                    MIN(%1$s) AS id_value_from,
                    MAX(%1$s) AS id_value_to
                FROM
                    %3$s
                GROUP BY
                    batch_number
                ORDER BY
                    id_value_from
            )
            SELECT
                id_value_from AS "id_from",
                id_value_to AS "id_to"
            FROM
                batches
            ',
                $autoIncrementField,
                self::BATCH_SIZE,
                $table
            )
        );
    }
}
