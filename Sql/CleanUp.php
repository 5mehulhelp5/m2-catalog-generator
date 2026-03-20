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

class CleanUp
{
    public const CLEAN_UP_QUERIES = [
        'START TRANSACTION',
        'SET FOREIGN_KEY_CHECKS = 0',
        // delete store data except ID = 0,
        'DELETE FROM `store` WHERE `store_id` > 0',
        'DELETE FROM `store_group` WHERE `group_id` > 0',
        'DELETE FROM `store_website` WHERE `website_id` > 0',

        // customer & group clean up
        'TRUNCATE TABLE `customer_address_entity`',
        'TRUNCATE TABLE `customer_address_entity_datetime`',
        'TRUNCATE TABLE `customer_address_entity_decimal`',
        'TRUNCATE TABLE `customer_address_entity_int`',
        'TRUNCATE TABLE `customer_address_entity_text`',
        'TRUNCATE TABLE `customer_address_entity_varchar`',
        'TRUNCATE TABLE `customer_entity`',
        'TRUNCATE TABLE `customer_entity_datetime`',
        'TRUNCATE TABLE `customer_entity_decimal`',
        'TRUNCATE TABLE `customer_entity_int`',
        'TRUNCATE TABLE `customer_entity_text`',
        'TRUNCATE TABLE `customer_entity_varchar`',
        //'TRUNCATE TABLE `customer_grid_flat`',

        // category cleanup
        'TRUNCATE TABLE `catalog_category_entity`',
        'TRUNCATE TABLE `catalog_category_entity_datetime`',
        'TRUNCATE TABLE `catalog_category_entity_decimal`',
        'TRUNCATE TABLE `catalog_category_entity_int`',
        'TRUNCATE TABLE `catalog_category_entity_text`',
        'TRUNCATE TABLE `catalog_category_entity_varchar`',
        'TRUNCATE TABLE `catalog_category_product`',

        // catalog clean up
        'TRUNCATE TABLE `catalog_product_entity`',
        'TRUNCATE TABLE `catalog_product_entity_datetime`',
        'TRUNCATE TABLE `catalog_product_entity_decimal`',
        'TRUNCATE TABLE `catalog_product_entity_gallery`',
        'TRUNCATE TABLE `catalog_product_entity_int`',
        'TRUNCATE TABLE `catalog_product_entity_media_gallery`',
        'TRUNCATE TABLE `catalog_product_entity_media_gallery_value`',
        'TRUNCATE TABLE `catalog_product_entity_media_gallery_value_to_entity`',
        'TRUNCATE TABLE `catalog_product_entity_media_gallery_value_video`',
        'TRUNCATE TABLE `catalog_product_entity_text`',
        'TRUNCATE TABLE `catalog_product_entity_tier_price`',
        'TRUNCATE TABLE `catalog_product_entity_varchar`',
        'TRUNCATE TABLE `catalog_product_frontend_action`',
        'TRUNCATE TABLE `catalog_product_link`',
        'TRUNCATE TABLE `catalog_product_link_attribute_decimal`',
        'TRUNCATE TABLE `catalog_product_link_attribute_int`',
        'TRUNCATE TABLE `catalog_product_link_attribute_varchar`',
        'TRUNCATE TABLE `catalog_product_option`',
        'TRUNCATE TABLE `catalog_product_option_price`',
        'TRUNCATE TABLE `catalog_product_option_title`',
        'TRUNCATE TABLE `catalog_product_option_type_price`',
        'TRUNCATE TABLE `catalog_product_option_type_title`',
        'TRUNCATE TABLE `catalog_product_option_type_value`',
        'TRUNCATE TABLE `catalog_product_relation`',
        'TRUNCATE TABLE `catalog_product_super_attribute`',
        'TRUNCATE TABLE `catalog_product_super_attribute_label`',
        'TRUNCATE TABLE `catalog_product_super_link`',
        'TRUNCATE TABLE `catalog_product_website`',

        // stock
        'TRUNCATE TABLE `cataloginventory_stock_item`',
        'TRUNCATE TABLE `cataloginventory_stock_status`',
        'TRUNCATE TABLE `inventory_low_stock_notification_configuration`',
        'TRUNCATE TABLE `inventory_source_stock_link`',
        "INSERT INTO `inventory_source_stock_link` (`stock_id`, `source_code`, `priority`) VALUES (1, 'default', 1)",
        'TRUNCATE TABLE `inventory_stock_sales_channel`',
        'TRUNCATE TABLE `product_alert_stock`',
        'TRUNCATE TABLE `inventory_source_item`',
        'DELETE FROM `inventory_stock` WHERE `stock_id` != 1',
        'ALTER TABLE `inventory_stock` AUTO_INCREMENT = 2',
        'DELETE FROM `inventory_source` WHERE `source_code` != \'default\'',

        // bundle product cleanup
        'TRUNCATE TABLE `catalog_product_bundle_option`',
        'TRUNCATE TABLE `catalog_product_bundle_option_value`',
        'TRUNCATE TABLE `catalog_product_bundle_selection`',
        'TRUNCATE TABLE `catalog_product_bundle_selection_price`',
        'TRUNCATE TABLE `catalog_product_bundle_selection_price`',

        // catalog rule
        'TRUNCATE TABLE `catalogrule`',
        'TRUNCATE TABLE `catalogrule_customer_group`',
        'TRUNCATE TABLE `catalogrule_group_website`',
        'TRUNCATE TABLE `catalogrule_product`',
        'TRUNCATE TABLE `catalogrule_product_price`',
        'TRUNCATE TABLE `catalogrule_website`',

        // sales rule
        'TRUNCATE TABLE `salesrule`',
        'TRUNCATE TABLE `salesrule_coupon`',
        'TRUNCATE TABLE `salesrule_coupon_aggregated`',
        'TRUNCATE TABLE `salesrule_coupon_aggregated_order`',
        'TRUNCATE TABLE `salesrule_coupon_aggregated_updated`',
        'TRUNCATE TABLE `salesrule_coupon_usage`',
        'TRUNCATE TABLE `salesrule_customer`',
        'TRUNCATE TABLE `salesrule_customer_group`',
        'TRUNCATE TABLE `salesrule_label`',
        'TRUNCATE TABLE `salesrule_product_attribute`',
        'TRUNCATE TABLE `salesrule_website`',

        // url rewrite
        'TRUNCATE TABLE `url_rewrite`', // you will lose all CMS pages links here

        // DELETE CUSTOM ATTRIBUTES
        'DELETE FROM `eav_attribute` WHERE `attribute_code` LIKE "%configurable%"',
        'DELETE FROM `eav_attribute` WHERE `attribute_code` LIKE "%dropdown_attribute%"',
        'DELETE FROM `eav_attribute` WHERE `attribute_code` REGEXP "^dropdown_[0-9]+$"',
        'DELETE FROM `eav_attribute` WHERE `attribute_code` REGEXP "^multiselect_[0-9]+$"',
        'DELETE FROM `customer_group` WHERE `customer_group_id` > 3',

        'SET FOREIGN_KEY_CHECKS = 1',
        'COMMIT',
    ];

    /** @var string[]  */
    public const WILDCARD_SUFFIXES = [
        '%_tmp', '%_replica', '%_index', '%_cl', '%_log'
    ];
}
