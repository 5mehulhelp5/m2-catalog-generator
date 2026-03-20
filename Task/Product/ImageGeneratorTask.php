<?php
// phpcs:ignoreFile - on purpose
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
use Qoliber\CatalogGenerator\Task\AbstractTask;

class ImageGeneratorTask extends AbstractTask implements TaskInterface
{
    /**
     * Run Task
     *
     * @return \Qoliber\CatalogGenerator\Api\Task\TaskInterface
     * @throws \Exception
     */
    public function runTask(): TaskInterface
    {
        $imageAttributeId = $this->getAttributeId($this->getEntityTypeId('catalog_product'), 'image');
        $smallImageAttributeId = $this->getAttributeId($this->getEntityTypeId('catalog_product'), 'small_image');
        $thumbnailImageAttributeId = $this->getAttributeId($this->getEntityTypeId('catalog_product'), 'thumbnail');
        $swatchImageId = $this->getAttributeId($this->getEntityTypeId('catalog_product'), 'swatch_image');
        $mediaGalleryAttributeId = $this->getAttributeId($this->getEntityTypeId('catalog_product'), 'media_gallery');
        $fastQuery = new InsertMultipleOnDuplicate();
        $productEntityBatches = $this->connection->getEntityBatches('entity_id', 'catalog_product_entity');

        $valueId = 1;
        foreach ($productEntityBatches as $batch) {
            $entityIdFrom = $batch['id_from'];
            $entityTo = $batch['id_to'];
            $dataToInsert = [
                'catalog_product_entity_varchar' => [],
                'catalog_product_entity_media_gallery' => [],
                'catalog_product_entity_media_gallery_value' => [],
                'catalog_product_entity_media_gallery_value_to_entity' => [],
            ];

            $query = $this->connection->getConnection()->select()
                ->from($this->connection->getTableName('catalog_product_entity'), ['entity_id', 'sku'])
                ->where('catalog_product_entity.entity_id >= ?', $entityIdFrom)
                ->where('catalog_product_entity.entity_id <= ?', $entityTo);

            foreach ($this->connection->getConnection()->fetchPairs($query) as $entityId => $productSku) {
                $imageName = sprintf('/%s/%s/%s.png', $productSku[0], $productSku[1], $productSku);
                $image = $this->generateImage();
                $this->saveFile(sprintf('catalog/product%s', $imageName), $image);

                $dataToInsert['catalog_product_entity_varchar'][] = [
                    'attribute_id' => $imageAttributeId,
                    'store_id' => 0,
                    'entity_id' => $entityId,
                    'value' => $imageName
                ];

                $dataToInsert['catalog_product_entity_varchar'][] = [
                    'attribute_id' => $smallImageAttributeId,
                    'store_id' => 0,
                    'entity_id' => $entityId,
                    'value' => $imageName
                ];

                $dataToInsert['catalog_product_entity_varchar'][] = [
                    'attribute_id' => $thumbnailImageAttributeId,
                    'store_id' => 0,
                    'entity_id' => $entityId,
                    'value' => $imageName
                ];

                $dataToInsert['catalog_product_entity_varchar'][] = [
                    'attribute_id' => $swatchImageId,
                    'store_id' => 0,
                    'entity_id' => $entityId,
                    'value' => $imageName
                ];

                $dataToInsert['catalog_product_entity_media_gallery'][] = [
                    'value_id' => $valueId,
                    'attribute_id' => $mediaGalleryAttributeId,
                    'value' => $imageName,
                    'media_type' => 'image',
                    'disabled' => 0
                ];

                $dataToInsert['catalog_product_entity_media_gallery_value'][] = [
                    'value_id' => $valueId,
                    'store_id' => 0,
                    'entity_id' => $entityId,
                    'label' => null,
                    'position' => 1,
                    'disabled' => 0,
                    'record_id' => $valueId
                ];

                $dataToInsert['catalog_product_entity_media_gallery_value_to_entity'][] = [
                    'value_id' => $valueId,
                    'entity_id' => $entityId
                ];

                $valueId++;
            }

            foreach ($dataToInsert as $tableName => $tableData) {
                foreach (array_chunk($tableData, 2500) as $dataBatch) {
                    $prepareStatement = $fastQuery->buildInsertQuery(
                        $tableName,
                        array_keys($dataBatch[0]),
                        count($dataBatch)
                    );

                    $this->connection->execute($prepareStatement, InsertMultipleOnDuplicate::flatten($dataBatch));
                }
            }
        }

        return $this;
    }

    /**
     * Generate Image, return image content to save
     *
     * @return string
     * @throws \Random\RandomException
     */
    private function generateImage(): string
    {
        $width = 500;
        $height = 500;
        $min_objects = 100;
        $max_objects = 150;
        $max_sides = 8;

        $img = imagecreatetruecolor($width, $height);

        $bg_color = (int) imagecolorallocatealpha(
            $img,
            random_int(0, 255),
            random_int(0, 255),
            random_int(0, 255),
            random_int(80, 100)
        );
        imagefilledrectangle($img, 0, 0, $width, $height, $bg_color);

        $geometric_avg = floor(sqrt($width * $height));
        $objects = random_int($min_objects, $max_objects);

        for ($i = 0; $i < $objects; $i++) {
            $obj_color = (int) imagecolorallocatealpha(
                $img,
                random_int(0, 255),
                random_int(0, 255),
                random_int(0, 255),
                random_int(80, 100)
            );

            $center_x = random_int(0, $width);
            $center_y = random_int(0, $height);
            $center_dist = random_int((int) ($geometric_avg / 8), (int) ($geometric_avg / 4));
            $sides = random_int(2, $max_sides);

            if ($sides < 3) {
                imagefilledellipse($img, $center_x, $center_y, $center_dist, $center_dist, $obj_color);
            } else {
                $points = [];
                $angle_diff = 360 / $sides;

                for ($j = 0; $j < $sides; $j++) {
                    $angle = deg2rad($angle_diff * $j);
                    $points[] = $center_x + $center_dist * cos($angle);
                    $points[] = $center_y + $center_dist * sin($angle);
                }
                imagefilledpolygon($img, $points, $obj_color);
            }
        }

        $black = (int) imagecolorallocate($img, 0, 0, 0);
        imagerectangle($img, 0, 0, $width - 1, $height - 1, $black);

        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
