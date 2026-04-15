# Database Design & Optimization — Interview Knowledge Base

Based on mock interview gaps identified on April 9, 2026.

---

## Diagnosing Slow Queries

### Step 1: EXPLAIN ANALYZE (not just EXPLAIN)
`EXPLAIN` shows the plan. `EXPLAIN ANALYZE` actually runs the query and shows real execution times.

**What to look for:**
- **type = ALL** → full table scan (bad on large tables). Want: `ref`, `range`, `const`
- **rows** → how many rows examined. 50,000,000 = no useful index
- **Extra: Using filesort** → sorting in memory/disk, not using index (slow)
- **Extra: Using temporary** → temp table created (slow)
- **key = NULL** → no index used

### Step 2: Composite Indexes (NOT single-column indexes)

**Three separate indexes on (merchant_id), (status), (created_at):**
MySQL picks ONE, narrows rows, scans the rest manually.

**One composite index on (merchant_id, status, created_at):**
MySQL uses ALL three columns efficiently in a single lookup.

**Column order rule: equality first, sort last.**

Query: `WHERE merchant_id = ? AND status = ? ORDER BY created_at DESC`
Index: `(merchant_id, status, created_at)`

MySQL finds exact merchant+status in the index, results are ALREADY sorted by created_at (B-tree order). No filesort needed.

Wrong order `(created_at, merchant_id, status)` — useless because MySQL can't skip the leftmost column.

### Step 3: Covering Indexes
If query only SELECTs certain columns, include them in the index:
```sql
CREATE INDEX idx_covering ON payments (merchant_id, status, created_at) INCLUDE (id, amount);
```
MySQL reads everything from the index — never touches the table ("index-only scan").

### Step 4: Partitioning (for very large tables)
Split table by a key. Queries only scan relevant partition:
```sql
-- Partition by month
PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at))
```
Query for "March 2026" only scans one partition, not 50M rows.

---

## N+1 Query Problem

1 query for N records + N queries for relationships = N+1 total.

**Fix:** Eager loading with `->with('relationship')`

```php
// BAD: 101 queries for 100 orders
$orders = Order::all();
foreach ($orders as $order) { echo $order->customer->name; }

// GOOD: 2 queries regardless of count
$orders = Order::with('customer')->get();
```

**Detection:** `Model::preventLazyLoading()` in development — throws exception on lazy loading.

---

## Key Concepts for Interviews

### Transaction Isolation Levels
- **Read Committed** (PostgreSQL default): sees committed data at statement start
- **Repeatable Read**: snapshot at transaction start, no phantom reads
- **Serializable**: full isolation, detects anomalies

For payments: minimum Repeatable Read to prevent double-spending. Use `SELECT FOR UPDATE` for explicit row locking.

### JSONB (PostgreSQL)
Store flexible product attributes. Index with GIN indexes. When to use: varying attributes per product type (clothes have size/color, electronics have specs).

### Zero-Downtime Migrations
**Expand-contract pattern:**
1. EXPAND: add new column (nullable/default), deploy code writing to both
2. MIGRATE: backfill in batches (not one huge UPDATE)
3. CONTRACT: remove old column after all code reads from new

Never: rename columns directly, add NOT NULL without default on large tables.
