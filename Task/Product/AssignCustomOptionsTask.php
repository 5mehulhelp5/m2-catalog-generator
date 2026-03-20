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

class AssignCustomOptionsTask extends AbstractProductTask implements TaskInterface
{
    /** @var string[] */
    private const OPTION_TYPES = ['drop_down', 'radio', 'checkbox'];

    /** @var string[] */
    private const OPTION_LABELS = [
        'Size', 'Color', 'Material', 'Style', 'Finish',
        'Pattern', 'Length', 'Width', 'Grade', 'Edition',
        'Warranty', 'Package', 'Engraving', 'Gift Wrap', 'Monogram',
    ];

    /** @var string[][] */
    private const OPTION_VALUES = [
        'Size' => ['Small', 'Medium', 'Large', 'X-Large', 'XX-Large'],
        'Color' => ['Red', 'Blue', 'Green', 'Black', 'White', 'Grey'],
        'Material' => ['Cotton', 'Polyester', 'Silk', 'Wool', 'Linen'],
        'Style' => ['Classic', 'Modern', 'Vintage', 'Casual', 'Formal'],
        'Finish' => ['Matte', 'Glossy', 'Satin', 'Brushed', 'Polished'],
        'Pattern' => ['Solid', 'Striped', 'Plaid', 'Floral', 'Abstract'],
        'Length' => ['Short', 'Regular', 'Long', 'Extra Long'],
        'Width' => ['Narrow', 'Standard', 'Wide', 'Extra Wide'],
        'Grade' => ['Standard', 'Premium', 'Professional', 'Elite'],
        'Edition' => ['Basic', 'Standard', 'Deluxe', 'Limited'],
        'Warranty' => ['1 Year', '2 Years', '3 Years', '5 Years'],
        'Package' => ['Single', 'Double Pack', 'Family Pack', 'Bulk'],
        'Engraving' => ['None', 'Initials', 'Full Name', 'Custom Text'],
        'Gift Wrap' => ['None', 'Standard', 'Premium', 'Luxury'],
        'Monogram' => ['None', '1 Letter', '2 Letters', '3 Letters'],
    ];

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
        $conn = $this->connection->getConnection();
        $productConfig = $this->configReader->getConfig('entities')['product'] ?? [];
        $config = $productConfig['custom_options'] ?? [];

        if (empty($config)) {
            return $this;
        }

        $count = (int) ($config['count'] ?? 0);
        $optionsPerProduct = (int) ($config['options_per_product'] ?? 3);
        $valuesPerOption = (int) ($config['values_per_option'] ?? 4);

        if ($count <= 0) {
            return $this;
        }

        $productIds = $conn->fetchCol(
            $conn->select()
                ->from($this->connection->getTableName('catalog_product_entity'), ['entity_id'])
                ->where('type_id = ?', 'simple')
                ->order('entity_id ASC')
                ->limit($count)
        );

        if (empty($productIds)) {
            return $this;
        }

        foreach ($productIds as $productId) {
            $availableLabels = self::OPTION_LABELS;
            shuffle($availableLabels);

            for ($i = 0; $i < $optionsPerProduct; $i++) {
                $label = $availableLabels[$i % count($availableLabels)];
                $type = self::OPTION_TYPES[array_rand(self::OPTION_TYPES)];

                $conn->insert(
                    $this->connection->getTableName('catalog_product_option'),
                    [
                        'product_id' => (int) $productId,
                        'type' => $type,
                        'is_require' => 1,
                        'sort_order' => $i + 1,
                    ]
                );

                $optionId = (int) $conn->lastInsertId();

                $conn->insert(
                    $this->connection->getTableName('catalog_product_option_title'),
                    [
                        'option_id' => $optionId,
                        'store_id' => 0,
                        'title' => $label,
                    ]
                );

                $values = self::OPTION_VALUES[$label] ?? ['Option A', 'Option B', 'Option C', 'Option D'];
                $valueCount = min($valuesPerOption, count($values));

                for ($j = 0; $j < $valueCount; $j++) {
                    $conn->insert(
                        $this->connection->getTableName('catalog_product_option_type_value'),
                        [
                            'option_id' => $optionId,
                            'sort_order' => $j + 1,
                        ]
                    );

                    $optionTypeId = (int) $conn->lastInsertId();

                    $conn->insert(
                        $this->connection->getTableName('catalog_product_option_type_title'),
                        [
                            'option_type_id' => $optionTypeId,
                            'store_id' => 0,
                            'title' => $values[$j],
                        ]
                    );

                    $conn->insert(
                        $this->connection->getTableName('catalog_product_option_type_price'),
                        [
                            'option_type_id' => $optionTypeId,
                            'store_id' => 0,
                            'price' => round(rand(100, 2000) / 100, 2),
                            'price_type' => 'fixed',
                        ]
                    );
                }
            }
        }

        $this->updateProductFlags($productIds);
        $this->createCategoryAndAssign($productIds);

        return $this;
    }

    /**
     * Update has_options and required_options flags
     *
     * @param string[] $productIds
     * @return void
     */
    private function updateProductFlags(array $productIds): void
    {
        $this->connection->getConnection()->update(
            $this->connection->getTableName('catalog_product_entity'),
            ['has_options' => 1, 'required_options' => 1],
            ['entity_id IN (?)' => $productIds]
        );
    }

    /**
     * Create "Custom Options Products" category and assign products
     *
     * @param string[] $productIds
     * @return void
     * @throws \Exception
     */
    private function createCategoryAndAssign(array $productIds): void
    {
        $conn = $this->connection->getConnection();
        $entityTypeId = $this->getEntityTypeId('catalog_category');
        $nameAttr = $this->getAttributeId($entityTypeId, 'name');
        $urlKeyAttr = $this->getAttributeId($entityTypeId, 'url_key');
        $urlPathAttr = $this->getAttributeId($entityTypeId, 'url_path');
        $isActiveAttr = $this->getAttributeId($entityTypeId, 'is_active');
        $menuAttr = $this->getAttributeId($entityTypeId, 'include_in_menu');
        $anchorAttr = $this->getAttributeId($entityTypeId, 'is_anchor');

        $parentId = (int) ($conn->fetchOne(
            $conn->select()->from($this->connection->getTableName('catalog_category_entity'), ['entity_id'])
                ->where('level = ?', 1)->order('entity_id ASC')->limit(1)
        ) ?: 2);

        $parentPath = (string) $conn->fetchOne(
            $conn->select()->from($this->connection->getTableName('catalog_category_entity'), ['path'])
                ->where('entity_id = ?', $parentId)
        );

        $parentLevel = (int) $conn->fetchOne(
            $conn->select()->from($this->connection->getTableName('catalog_category_entity'), ['level'])
                ->where('entity_id = ?', $parentId)
        );

        $attrSetId = (int) $conn->fetchOne(
            $conn->select()->from($this->connection->getTableName('eav_entity_type'), ['default_attribute_set_id'])
                ->where('entity_type_code = ?', 'catalog_category')
        );

        $catId = (int) $conn->fetchOne(
            $conn->select()->from($this->connection->getTableName('catalog_category_entity'), ['MAX(entity_id)'])
        ) + 1;

        $conn->insert($this->connection->getTableName('catalog_category_entity'), [
            'entity_id' => $catId,
            'attribute_set_id' => $attrSetId,
            'parent_id' => $parentId,
            'path' => sprintf('%s/%d', $parentPath, $catId),
            'position' => 10,
            'level' => $parentLevel + 1,
            'children_count' => 0,
        ]);

        $varchar = $this->connection->getTableName('catalog_category_entity_varchar');
        $int = $this->connection->getTableName('catalog_category_entity_int');

        foreach ([
            [$varchar, $nameAttr, 'Custom Options Products'],
            [$varchar, $urlKeyAttr, 'custom-options-products'],
            [$varchar, $urlPathAttr, 'custom-options-products'],
            [$int, $isActiveAttr, 1],
            [$int, $menuAttr, 1],
            [$int, $anchorAttr, 1],
        ] as [$table, $attrId, $value]) {
            $conn->insert($table, [
                'attribute_id' => $attrId,
                'store_id' => 0,
                'entity_id' => $catId,
                'value' => $value,
            ]);
        }

        $data = [];
        $pos = 0;

        foreach ($productIds as $pid) {
            $data[] = [
                'category_id' => $catId,
                'product_id' => (int) $pid,
                'position' => $pos++,
            ];
        }

        $insert = new InsertMultipleOnDuplicate();

        foreach (array_chunk($data, 2500) as $batch) {
            $stmt = $insert->buildInsertQuery('catalog_category_product', array_keys($batch[0]), count($batch));
            $this->connection->execute($stmt, InsertMultipleOnDuplicate::flatten($batch));
        }
    }
}
