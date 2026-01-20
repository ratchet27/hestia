# Stock Entry Refactoring Design

## Overview

Replace aggregate-based stock tracking with entry-per-item model. Each unit is one row. Consume = delete. No quantity tracking, no event log.

## Data Model

### StockEntry (new, replaces Stock)

```
Table: stock_entries
├── id (UUID, PK)
├── product_id (FK → Product, NOT NULL)
├── location_id (FK → Location, NOT NULL)
├── best_before (DATE, nullable)
└── created_at (TIMESTAMP, NOT NULL, default NOW)
```

No `quantity` field — each row represents exactly one unit.

**Indexes:**
- `(product_id, location_id, best_before NULLS LAST, created_at)` — FIFO ordering within location
- `(product_id)` — total quantity queries (COUNT)
- `(location_id)` — "what's in fridge" queries
- `(best_before) WHERE best_before IS NOT NULL` — expiring soon

### Product (modify existing)

Add field:
```
unit (VARCHAR 50, default 'piece') — display label: "carton", "piece", "bag"
```

### Delete

- `Stock` entity
- `StockMovement` entity
- `StockMovementType` enum

## Operations

### Add Stock

```
POST /stocks/add
{
  "product_id": "uuid",
  "location_id": "uuid",
  "quantity": 3,
  "best_before": "2026-02-15"  // optional
}
```

Creates N rows in `stock_entries`, each with same `product_id`, `location_id`, `best_before`.

**best_before logic:**
- If provided in request: use that value
- Else if product has `default_expiry_days`: calculate `today + default_expiry_days`
- Else: NULL

### Consume Stock

```
POST /stocks/consume
{
  "product_id": "uuid",
  "location_id": "uuid",
  "quantity": 2
}
```

All three fields required. Deletes N entries in FIFO order within that location.

**FIFO order:**
1. Earliest `best_before` first
2. `NULL` best_before sorts LAST
3. Tiebreaker: earliest `created_at`

**Error handling:** If fewer entries exist than requested, reject entirely with `InsufficientStockException`. No partial consumption.

**Response:**
```json
{
  "consumed": 2,
  "deleted_entries": ["entry-uuid-1", "entry-uuid-2"],
  "remaining_at_location": 3
}
```

### Update Entry

```
PATCH /stocks/entries/{id}
{
  "location_id": "new-uuid",    // optional
  "best_before": "2026-02-20"   // optional
}
```

Updates single entry's location and/or best_before.

### Delete Entry

```
DELETE /stocks/entries/{id}
```

Manual removal (spoiled, damaged, etc.).

## API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/stocks` | Summary by product |
| GET | `/stocks?low_stock=true` | Products where count < min_stock |
| GET | `/stocks/entries` | List individual entries |
| GET | `/stocks/entries?location={id}` | Filter by location |
| GET | `/stocks/entries?product={id}` | Filter by product |
| GET | `/stocks/expiring?days=7` | Entries expiring within N days (includes expired) |
| POST | `/stocks/add` | Create N entries |
| POST | `/stocks/consume` | Delete N entries (FIFO) |
| PATCH | `/stocks/entries/{id}` | Update entry (location, best_before) |
| DELETE | `/stocks/entries/{id}` | Delete single entry |

## Response Formats

### GET /stocks

```json
{
  "data": [{
    "product": {"id": "...", "name": "Milk", "unit": "carton"},
    "total_quantity": 5,
    "earliest_expiry": "2026-01-25",
    "locations": [
      {"id": "...", "name": "Fridge", "quantity": 3},
      {"id": "...", "name": "Garage", "quantity": 2}
    ]
  }]
}
```

### GET /stocks/entries

```json
{
  "data": [{
    "id": "entry-uuid",
    "product": {"id": "...", "name": "Milk", "unit": "carton"},
    "location": {"id": "...", "name": "Fridge"},
    "best_before": "2026-01-25",
    "created_at": "2026-01-18T10:30:00Z"
  }]
}
```

### GET /stocks/expiring?days=7

Returns entries with `best_before` within range, ordered by urgency (most negative first).

```json
{
  "data": [{
    "id": "entry-uuid",
    "product": {"id": "...", "name": "Milk", "unit": "carton"},
    "location": {"id": "...", "name": "Fridge"},
    "best_before": "2026-01-17",
    "days_until_expiry": -2
  }]
}
```

Includes:
- Already expired (days_until_expiry < 0) — most urgent
- Expiring today (days_until_expiry = 0)
- Expiring within N days

## Migration Strategy

1. Create `stock_entries` table
2. Add `unit` column to `products` (default 'piece')
3. Migrate existing `stocks` data:
   - For each Stock record with quantity N, create N entries
   - `best_before = NULL` (unknown for legacy)
   - `created_at = NOW()`
4. Drop `stocks` and `stock_movements` tables

## Files

### Create

- `src/Entity/StockEntry.php`
- `src/Repository/StockEntryRepository.php`
- `src/Service/StockEntryService.php`
- `src/Request/AddStockRequest.php`
- `src/Request/ConsumeStockRequest.php`
- `src/Request/UpdateStockEntryRequest.php`
- `src/Response/Stock/StockEntryResponse.php`
- `src/Response/Stock/StockSummaryResponse.php`
- `src/Response/Stock/ConsumeResultResponse.php`
- `src/Response/Stock/ExpiringEntryResponse.php`
- `src/Factory/StockEntryFactory.php`
- `migrations/VersionXXXX_StockEntries.php`
- `tests/Controller/Api/Internal/V1/StockControllerTest.php`
- `tests/Service/StockEntryServiceTest.php`

### Modify

- `src/Entity/Product.php` — add `unit` field
- `src/Controller/Api/Internal/V1/StockController.php` — rewrite endpoints
- `src/Exception/Stock/InsufficientStockException.php` — update message

### Delete

- `src/Entity/Stock.php`
- `src/Entity/StockMovement.php`
- `src/Entity/StockMovementType.php`
- `src/Repository/StockRepository.php`
- `src/Repository/StockMovementRepository.php`
- `src/Service/StockService.php`
- `src/Request/CreateStockMovementRequest.php`
- `src/Response/Stock/StockResponse.php`
- `src/Response/Stock/StockMovementResponse.php`
- `src/Response/Stock/StockLocationResponse.php`
- `src/Factory/StockFactory.php`
- `src/Factory/StockMovementFactory.php`

## Test Scenarios

### FIFO Logic
- Consume takes earliest best_before first
- NULL best_before consumed last
- Same best_before: earliest created_at wins
- Consume spans multiple entries correctly

### Add
- quantity=3 creates 3 entries
- best_before auto-calculated from product.default_expiry_days
- best_before override works
- No default_expiry_days → best_before NULL

### Consume
- Deletes correct number of entries
- InsufficientStockException when not enough
- Location scoping works correctly

### Queries
- `/stocks` aggregates correctly (COUNT per product)
- `/stocks?low_stock=true` filters by min_stock
- `/stocks/entries?location=X` filters correctly
- `/stocks/entries?product=X` filters correctly
- `/stocks/expiring?days=N` includes expired, orders by urgency

### Edge Cases
- Consume exact available quantity
- Consume from product with no entries → error
- Entry with NULL best_before
- Move entry to different location via PATCH
- Update best_before via PATCH
