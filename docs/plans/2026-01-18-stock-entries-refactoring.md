# Stock Management Refactoring Plan

## Overview

Refactor from aggregate-based tracking (one Stock record per product+location) to lot-based tracking with expiry dates and FIFO consumption.

## 1. Data Model

### StockEntry Entity (replaces Stock)

```
Table: stock_entries
├── id (UUID, PK)
├── product_id (FK, NOT NULL)
├── location_id (FK, NOT NULL)
├── quantity (INT, >= 0) — remaining quantity, decrements on consume
├── best_before (DATE, nullable)
├── purchased_at (DATE, NOT NULL)
├── note (VARCHAR 500, nullable)
└── created_at (TIMESTAMP)
```

**Indexes:**
- `(product_id, location_id, best_before NULLS LAST, purchased_at)` — FIFO ordering
- `(product_id)` — summary queries
- `(location_id)` — location filtering
- `(best_before) WHERE best_before IS NOT NULL AND quantity > 0` — expiring soon

### StockEvent Entity (replaces StockMovement)

```
Table: stock_events
├── id (UUID, PK)
├── type (ENUM: ADDED, CONSUMED, ADJUSTED, TRANSFERRED)
├── product_id (FK, NOT NULL)
├── payload (JSON) — event-specific details
├── note (VARCHAR 500, nullable)
└── created_at (TIMESTAMP)
```

**Rationale for event log:** Consumption can affect multiple entries, so a single movement record per entry doesn't work. JSON payload provides flexibility for different event types.

## 2. Migration Strategy

1. **Create** `stock_entries` table (additive, no downtime)
2. **Migrate** existing Stock records:
   - Copy product_id, location_id, quantity
   - Set `best_before = NULL` (unknown for legacy data)
   - Set `purchased_at = Stock.updatedAt` or migration date
   - Set `note = 'Migrated from legacy stock system'`
3. **Archive** old `stock_movements` (events start fresh)
4. **Drop** old tables after verification

## 3. API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/stocks` | List summaries (aggregated by product+location) |
| GET | `/stocks/entries` | List individual entries |
| GET | `/stocks/entries/{id}` | Get single entry |
| GET | `/stocks/expiring?days=N` | Entries expiring within N days |
| POST | `/stocks/add` | Create new entry (purchase) |
| POST | `/stocks/consume` | Consume via FIFO |
| POST | `/stocks/entries/{id}/adjust` | Adjust specific entry quantity |
| POST | `/stocks/entries/{id}/transfer` | Move entry to different location |

### Request Examples

**POST /stocks/add**
```json
{
  "product_id": "uuid",
  "location_id": "uuid",
  "quantity": 10,
  "best_before": "2024-06-15",
  "purchased_at": "2024-01-18",
  "note": "Supplier batch #123"
}
```

**POST /stocks/consume**
```json
{
  "product_id": "uuid",
  "location_id": "uuid",  // optional, omit to consume from all locations
  "quantity": 5,
  "note": "Used for order #456"
}
```

**POST /stocks/entries/{id}/adjust**
```json
{
  "quantity": 8,
  "note": "Inventory count correction"
}
```

**POST /stocks/entries/{id}/transfer**
```json
{
  "target_location_id": "uuid",
  "note": "Moving to cold storage"
}
```

## 4. FIFO Consumption Logic

**Priority Order:**
1. Earliest `best_before` date first
2. `NULL` best_before sorts LAST (no expiry = consume after dated items)
3. Within same best_before: earliest `purchased_at` first

**Algorithm:**
```
entries = SELECT * FROM stock_entries
  WHERE product_id = ? AND quantity > 0
  ORDER BY best_before ASC NULLS LAST, purchased_at ASC

remaining = requested_quantity
for entry in entries:
    if remaining <= 0: break
    consume = min(entry.quantity, remaining)
    entry.quantity -= consume
    remaining -= consume

if remaining > 0:
    throw InsufficientStockException
```

## 5. Decision: StockMovement

**Recommendation: Replace with StockEvent (event log)**

| Current | New |
|---------|-----|
| StockMovement tied to single Stock | StockEvent at product level |
| ADD/REMOVE/ADJUST types | ADDED/CONSUMED/ADJUSTED/TRANSFERRED |
| Fixed schema | JSON payload for flexibility |

**Why:** FIFO consumption affects multiple entries. Event log with JSON payload cleanly captures "consumed 3 from entry A, 2 from entry B" in single record.

## 6. Files to Create/Modify

### New Files
- `src/Entity/StockEntry.php`
- `src/Entity/StockEvent.php`
- `src/Entity/StockEventType.php`
- `src/Repository/StockEntryRepository.php`
- `src/Repository/StockEventRepository.php`
- `src/Service/StockEntryService.php`
- `src/Request/AddStockRequest.php`
- `src/Request/ConsumeStockRequest.php`
- `src/Request/AdjustStockEntryRequest.php`
- `src/Request/TransferStockEntryRequest.php`
- `src/Response/Stock/StockEntryResponse.php`
- `src/Response/Stock/ConsumeStockResponse.php`
- `src/Response/Stock/ExpiringStockResponse.php`
- `src/Factory/StockEntryFactory.php`
- `src/Factory/StockEventFactory.php`
- `migrations/VersionXXXX_StockEntries.php`
- `tests/Functional/.../StockEntryControllerTest.php`
- `tests/Unit/Service/StockEntryServiceTest.php`

### Modified Files
- `src/Controller/Api/Internal/V1/StockController.php` — rewrite endpoints
- `src/Response/Stock/StockSummaryResponse.php` — add earliest_expiry, expiring_soon_count

### Delete After Migration
- `src/Entity/Stock.php`
- `src/Entity/StockMovement.php`
- `src/Repository/StockRepository.php`
- `src/Repository/StockMovementRepository.php`
- `src/Service/StockService.php`
- `src/Request/CreateStockMovementRequest.php`
- `src/Response/Stock/StockResponse.php`
- `src/Response/Stock/StockMovementResponse.php`
- `src/Factory/StockFactory.php`
- `src/Factory/StockMovementFactory.php`

## 7. Test Scenarios

### FIFO Logic
- Consume depletes earliest best_before first
- NULL best_before consumed after dated entries
- Same best_before: use purchased_at as tiebreaker
- Consumption spans multiple entries correctly
- InsufficientStockException when quantity unavailable

### Operations
- ADD creates entry with correct fields
- CONSUME returns affected entries with consumed amounts
- ADJUST modifies specific entry only
- TRANSFER changes location, preserves other fields

### Edge Cases
- Consume exact available quantity
- Adjust quantity to zero
- Transfer to same location (should fail)
- Multiple entries for same product+location

### Integration
- Expiring endpoint filters by days correctly
- Summary includes earliest_expiry across entries
- Low stock filter uses sum of entry quantities

## 8. Verification

After implementation:
1. Run `bin/console doctrine:schema:validate`
2. Run full test suite: `bin/phpunit`
3. Test migration on copy of production data
4. Manual API testing:
   - Add entries with various best_before dates
   - Verify FIFO order via consume
   - Check expiring endpoint accuracy
