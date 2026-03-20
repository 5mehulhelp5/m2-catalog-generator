<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Data\Populators;

use Magento\Framework\App\ResourceConnection;
use Qoliber\CatalogGenerator\Api\DataPopulatorInterface;

abstract class AbstractAttributePopulator implements DataPopulatorInterface
{
    /**
     * @param \Magento\Framework\App\ResourceConnection $connection
     * @param mixed[] $attributeIdArray
     */
    public function __construct(
        protected ResourceConnection $connection,
        private array $attributeIdArray = []
    ) {
    }

    /**
     * Get Attribute Data
     *
     * @param string $entityId
     * @param string $attributeCode
     * @return array<string, array<int, string>>
     */
    public function getAttributeData(string $entityId, string $attributeCode):  array
    {
        if (!isset($this->attributeIdArray[$entityId][$attributeCode])) {
            $eavAttributeTable = $this->connection->getConnection()->getTableName('eav_attribute');
            $query = $this->connection
                ->getConnection()
                ->select()
                ->from($eavAttributeTable)
                ->where('entity_type_id = ?', $entityId)
                ->where('attribute_code = ?', $attributeCode);

            $attributeData = $this->connection->getConnection()->fetchRow($query);

            if (!isset($this->attributeIdArray[$entityId])) {
                $this->attributeIdArray[$entityId] = [];
            }

            if ($attributeData) {
                $this->attributeIdArray[$entityId][$attributeCode] = [
                    'attribute_id' => (int) $attributeData['attribute_id'],
                    'backend_type' => $attributeData['backend_type']
                ];
            }
        }

        return $this->attributeIdArray[$entityId][$attributeCode] ?? [];
    }
}
