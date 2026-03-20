# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-03-20
### Added
- **MSI Multi-Source Inventory**: `StockGenerator` creates configurable MSI sources and stocks
- **MSI Source-Stock Linking**: `LinkSourcesTask` links all sources to all stocks
- **Multi-source product stock**: `AssignStockTask` assigns inventory across all sources, not just default
- **Multi-stock sales channels**: `AssignSalesChannel` distributes websites across stocks round-robin
- **Catalog Rules Generator**: `CatalogRuleGenerator` creates price rules with random discounts, linked to all websites and customer groups
- **Custom EAV Attributes**: `CustomAttributeGenerator` creates dropdown and multiselect attributes with configurable options, searchable/filterable flags
- **Custom Attribute Assignment**: `AssignCustomAttributesTask` randomly assigns attribute values to products based on configurable percentage
- **Custom Product Options**: `AssignCustomOptionsTask` creates dropdown/radio/checkbox options with realistic labels (Size, Color, Material, etc.) and random pricing
- **Custom Options Category**: Automatically creates "Custom Options Products" category with menu visibility
- **Tier Prices**: `AssignTierPricesTask` generates percentage-based tier prices with configurable levels (e.g. 5 levels = 1-5% discount at qty 2/5/10/20/50)
- **Type Categories**: `AssignToTypeCategoriesTask` auto-creates "Configurable Products", "Bundle Products", "Grouped Products" categories with is_anchor, include_in_menu
- **Command Generator**: `CommandGenerator` stub to separate reindex/cache tasks from product entity
- **Realistic Product Names**: `NameResolver` generates names from adjective + material + product pools (e.g. "Alpine Merino Jacket", "Cedar Sunglasses")
- **Realistic Category Names**: `CategoryNameResolver` with 100+ real department names (e.g. "Electronics", "Garden & Patio", "Men's Fashion")
- **Configurable resolver system**: YAML `resolver:name` / `resolver:category_name` dispatches to correct resolver
- **Symfony Progress Bars**: All product types and tasks show real-time progress with %, elapsed time, estimated time, and memory usage
- **Batched Product Generation**: Products generated and flushed to DB in 5,000-row batches instead of monolithic array (reduced peak memory from ~4 GB to ~250 MB)
- **Chunked SQL Inserts**: `CatalogGenerationService` inserts in 5,000-row chunks preventing "MySQL server has gone away" errors
- **Per-website root categories**: Category tree first level creates one root per website
- **Sample configs**: `big.yml` for large-scale testing (~240k+ products, 800 categories, 3 websites, MSI, 20 custom attributes)
- **Sample configs**: `test.yml` for quick validation runs

### Changed
- **CustomerGroupGenerator**: Fully implemented (was TODO stub), creates N additional groups with tax_class_id=3
- **ProductGenerator**: Refactored to flush per-type batches with progress bars instead of building monolithic array
- **AssignToCategories**: Batched per entity batch to reduce memory, fixed crash with small category counts
- **AssignToWebsitesTask**: Batched per entity batch to reduce memory
- **BundleProductDataGenerator**: Added required EAV attributes (price_type, sku_type, weight_type, price_view, shipment_type=1), product relations, fixing bundle visibility and price indexing
- **WebsiteGenerator**: First store view preserves code `default` for Magento StoreResolver compatibility
- **AbstractGenerator**: `getAttributeValue()` now resolves any `resolver:{name}` pattern, not just hardcoded `name`
- **Connection::execute()**: Removed redundant double `flatten()` call
- **All YAML samples updated**: test, small, medium, large, big configs all include complete feature set
- **CleanUpService**: Drops old MSI stock views, handles view/table errors gracefully

### Fixed
- **Bundle products invisible on frontend**: Missing `has_options`/`required_options` flags and bundle-specific EAV attributes
- **Bundle price index empty**: Missing `catalog_product_relation` entries for bundles
- **Bundle MSI conflict**: Set `shipment_type=1` (Ship Separately) to avoid MSI multi-source validation errors
- **Configurable products not addable to cart**: Default source-stock link (`stock_id=1 -> default`) was truncated by cleanup, now restored
- **Category attribute crash**: `AbstractAttributePopulator::getAttributeData()` returned null key access when attribute not found
- **Product attribute crash**: Same null guard added to `ProductGenerator::populateAttributes()`
- **AssignToCategories crash**: `array_rand()` failed with small category counts, fixed with proper bounds
- **Store not found after generation**: Cleanup deleted all stores including default, first store now always gets code `default`
- **MySQL server gone away**: Single mega-INSERT with 1M+ placeholders exceeded MySQL limits, now chunked
- **MSI stock views missing**: `LinkSourcesTask` now auto-creates `inventory_stock_{id}` views required by the MSI indexer
- **MSI stock ID drift**: Reset `inventory_stock` auto-increment after cleanup to prevent ever-growing stock IDs
- **Cleanup crash on views**: Wildcard truncation now catches exceptions for views and missing tables

### Cleanup
- MSI cleanup: Truncates `inventory_source_stock_link` (restores default link), deletes custom stocks and sources
- Customer group cleanup: `DELETE FROM customer_group WHERE customer_group_id > 3` preserves defaults
- Custom attribute cleanup: Regex-based deletion for `dropdown_N` and `multiselect_N` patterns
- Separated reindex/cache tasks into `commands` entity for cleaner YAML structure

## [1.0.2] - 2025-02-06
### Changed
- Version bump and maintenance updates

## [1.0.1] - 2025-01-15
### Changed
- Updated license information in composer.json

## [1.0.0] - 2024-12-16
### Added
- Initial release of Magento 2 Catalog Generator module for performance testing and demo data
- Implemented YAML-based catalog configuration system for flexible test data generation
- Added support for generating simple products with randomized attributes
- Added support for generating configurable products with automatic variant creation
- Added support for generating bundle products with dynamic option sets
- Added support for generating grouped products with associated product links
- Implemented high-performance SQL-based data insertion bypassing Magento ORM
- Added category tree generation with configurable depth and breadth
- Implemented website and store view generation for multi-store testing
- Added customer group generation for testing group-specific pricing
- Implemented automatic product attribute population with random values
- Added configurable attribute combinator for creating product variants
- Implemented automatic URL key generation for products and categories
- Added automatic URL rewrite generation for SEO testing
- Implemented product-to-category assignment with configurable distribution
- Added product-to-website assignment for multi-website environments
- Implemented stock/inventory assignment for all generated products
- Added randomized product image generation using placeholder services
- Implemented cleanup service to wipe existing catalog data before generation
- Added three sample YAML configurations (small, medium, large) for different catalog sizes
- Implemented reindex and cache flush tasks as post-generation operations
- Added developer mode requirement for safety (prevents accidental production use)
- Implemented modular architecture with generators, populators, and task processors
- Added YAML configuration reader with validation
- Implemented name resolver for handling entity references in configuration
- Added console command `qoliber:catalog:generate` for triggering catalog generation
- Implemented progress tracking and performance metrics during generation
- Added comprehensive README with usage examples and architecture documentation

### Security
- Added developer mode restriction to prevent execution in production environments
- Implemented complete catalog wipe warning to prevent accidental data loss
