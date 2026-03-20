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

use Qoliber\CatalogGenerator\Api\EntityGeneratorInterface;

class CatalogRuleGenerator extends AbstractGenerator implements EntityGeneratorInterface
{
    /**
     * Get Entity Table
     *
     * @return string
     */
    public function getEntityTable(): string
    {
        return 'catalogrule';
    }

    /**
     * Generate Entities. return entity array
     *
     * @param int|string $count
     * @param string $entityType
     * @param mixed[] $entityConfig
     * @return mixed[][]
     */
    public function generateEntities(int|string $count, string $entityType, array $entityConfig = []): array
    {
        $count = (int) $count;

        if ($count <= 0) {
            return [];
        }

        $prefix = strtolower((string) $this->configReader->getConfig('prefix'));
        $discountType = $entityConfig['discount_type'] ?? 'by_percent';
        $discountRange = $entityConfig['discount_range'] ?? [5, 30];
        $discountMin = (int) $discountRange[0];
        $discountMax = (int) $discountRange[1];

        $conn = $this->resourceConnection->getConnection();
        $websiteIds = $this->getWebsiteIds();
        $customerGroupIds = $this->getCustomerGroupIds();

        $ruleIds = [];

        for ($i = 1; $i <= $count; $i++) {
            $discount = rand($discountMin, $discountMax);
            $conn->insert(
                $conn->getTableName('catalogrule'),
                [
                    'name' => sprintf('%s Catalog Rule %d', ucfirst($prefix), $i),
                    'description' => sprintf('Auto-generated catalog rule %d', $i),
                    'from_date' => null,
                    'to_date' => null,
                    'is_active' => 1,
                    'conditions_serialized' => '{"type":"Magento\\\\CatalogRule\\\\Model\\\\Rule\\\\Condition\\\\Combine","attribute":null,"operator":null,"value":"1","is_value_processed":null,"aggregator":"all"}',
                    'actions_serialized' => '{"type":"Magento\\\\CatalogRule\\\\Model\\\\Rule\\\\Action\\\\Collection","attribute":null,"operator":null,"value":"1","is_value_processed":null,"aggregator":"all"}',
                    'stop_rules_processing' => 0,
                    'sort_order' => $i,
                    'simple_action' => $discountType,
                    'discount_amount' => $discount,
                ]
            );

            $ruleIds[] = (int) $conn->lastInsertId();
        }

        $ruleWebsiteData = [];
        $ruleGroupData = [];
        $ruleGroupWebsiteData = [];

        foreach ($ruleIds as $ruleId) {
            foreach ($websiteIds as $websiteId) {
                $ruleWebsiteData[] = [
                    'rule_id' => $ruleId,
                    'website_id' => (int) $websiteId,
                ];

                foreach ($customerGroupIds as $groupId) {
                    $ruleGroupWebsiteData[] = [
                        'rule_id' => $ruleId,
                        'customer_group_id' => (int) $groupId,
                        'website_id' => (int) $websiteId,
                    ];
                }
            }

            foreach ($customerGroupIds as $groupId) {
                $ruleGroupData[] = [
                    'rule_id' => $ruleId,
                    'customer_group_id' => (int) $groupId,
                ];
            }
        }

        $result = [];

        if (!empty($ruleWebsiteData)) {
            $result['catalogrule_website'] = $ruleWebsiteData;
        }

        if (!empty($ruleGroupData)) {
            $result['catalogrule_customer_group'] = $ruleGroupData;
        }

        if (!empty($ruleGroupWebsiteData)) {
            $result['catalogrule_group_website'] = $ruleGroupWebsiteData;
        }

        return $result;
    }

    /**
     * Populate attributes
     *
     * @param mixed[] $entityConfig
     * @param int $entityId
     * @return mixed[]
     */
    public function populateAttributes(array $entityConfig, int $entityId): array
    {
        return [];
    }

    /**
     * Get all website IDs
     *
     * @return string[]
     */
    private function getWebsiteIds(): array
    {
        $conn = $this->resourceConnection->getConnection();

        return $conn->fetchCol(
            $conn->select()
                ->from($conn->getTableName('store_website'), ['website_id'])
                ->where('website_id > 0')
        );
    }

    /**
     * Get all customer group IDs
     *
     * @return string[]
     */
    private function getCustomerGroupIds(): array
    {
        $conn = $this->resourceConnection->getConnection();

        return $conn->fetchCol(
            $conn->select()
                ->from($conn->getTableName('customer_group'), ['customer_group_id'])
        );
    }
}
