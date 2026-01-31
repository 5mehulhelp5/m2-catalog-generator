# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
