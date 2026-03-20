# Planned Improvements

## 1. Batch Insert per Product Type (Critical)
Currently, all product data is built in memory as one massive array before inserting.
For 1.1M products this means ~12M+ array entries (~2-3GB RAM) with zero progress feedback.

**Goal:** Insert data to database after each product type iteration:
- Simple products generated → insert immediately → free memory
- Configurable products generated → insert immediately → free memory
- Bundle products generated → insert immediately → free memory
- Grouped products generated → insert immediately → free memory

**Implementation:** Refactor `ProductGenerator::generateEntities()` to yield/flush per type,
or have the service call `generateEntities()` per product type with chunking.

**Benefits:**
- Lower peak memory (flush array after each batch insert)
- Progress visibility (output after each type completes)
- Partial data survives crashes
- Enables per-type progress bars / counters

## 2. Fix Double Array Flattening (Quick Win)
`InsertMultipleOnDuplicate::flatten()` is called twice:
- Once in `CatalogGenerationService` line 90
- Again inside `Connection::execute()` line 87

For 12M+ attribute rows this wastes significant CPU and creates unnecessary temporary arrays.

**Fix:** Remove flatten from `Connection::execute()` — callers already flatten.

## 3. Chunk Large Inserts (Critical)
The service builds a single INSERT statement with 1M+ rows of placeholders.
MySQL can fail or run out of memory on mega-statements.

**Fix:** Chunk inserts in `CatalogGenerationService` into batches of 5,000-10,000 rows,
similar to how tasks already chunk with `array_chunk($data, 2500)`.

## 4. Batch Bundle/Grouped Inserts (High Impact)
`BundleProductDataGenerator::populateRequiredTables()` does individual `$connection->insert()`
for each option and selection. For 25k bundles × ~20 inserts = 500k individual round-trips.

**Fix:** Accumulate bundle_option, bundle_selection, product_link rows and batch insert.
Challenge: `lastInsertId()` dependency for option_id linking.

## 5. Pre-compute Attribute Metadata (Medium)
`ProductGenerator::populateAttributes()` calls `getTableName()` for every attribute of every product.
With 1.1M products × 10 attributes = 11M calls.

**Fix:** Build a lookup table of attribute_code → (attribute_id, table_name) at start.

## 6. Symfony ProgressBar for CLI Output
Replace plain text output with Symfony ProgressBar (already available via Console).
Requires batching (improvement #1) to advance the bar per chunk.

```
 --> Generating simple products
 [████████████████░░░░░░░░░░░░░░] 55% (137,500 / 250,000)
 --> Generating configurable products
 [██████░░░░░░░░░░░░░░░░░░░░░░░░] 20% (5,000 / 25,000)
     ↳ Assigning stock [████████████████████░░░░░░░░░░] 67% (batch 4/6)
```

**Implementation:**
- Pass `OutputInterface` through to generators and tasks
- Create a ProgressBar per product type / task
- Advance per batch flush
- Show elapsed time and memory usage per step

## 7. Image Generation Optimization
- Current implementation generates individual PNG files — not viable for 1M+ products
- Consider: placeholder image reuse, symlinks, or on-demand generation
