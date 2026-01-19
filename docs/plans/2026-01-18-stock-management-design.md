# Stock Management Backend Design

**Date:** 2026-01-18
**Status:** Approved
**Scope:** Backend API only (frontend in separate iteration)

## Overview

Implement stock management for Hestia - tracking inventory quantities per product per location, with movement history for auditing.

## Data Model

### Stock Entity

Tracks current quantity per product/location combination (denormalized for fast reads).

| Field | Type | Constraints |
|-------|------|-------------|
| id | UUID | Primary key |
| product_id | UUID | FK → Product, NOT NULL |
| location_id | UUID | FK → Location, NOT NULL |
| quantity | INT | Default 0, >= 0 |
| updated_at | TIMESTAMP | Auto-updated |

**Indexes:**
- UNIQUE INDEX on (product_id, location_id) - prevents duplicates
- INDEX on product_id - for product queries
- INDEX on location_id - for location queries

### StockMovement Entity

Audit log of all stock changes.

| Field | Type | Constraints |
|-------|------|-------------|
| id | UUID | Primary key |
| stock_id | UUID | FK → Stock, NOT NULL |
| type | ENUM | ADD, REMOVE, ADJUST |
| quantity | INT | NOT NULL |
| notes | VARCHAR(255) | Nullable, optional reason |
| created_at | TIMESTAMP | Auto-set |

**Movement Logic:**
- `ADD`: stock.quantity += movement.quantity
- `REMOVE`: stock.quantity -= movement.quantity (fails if result < 0)
- `ADJUST`: stock.quantity = movement.quantity (sets absolute value)

## API Endpoints

### Create Movement

Primary action endpoint for all stock changes.

```
POST /api/internal/v1/stocks/movements

Request:
{
  "product_id": "uuid",
  "location_id": "uuid",
  "type": "ADD" | "REMOVE" | "ADJUST",
  "quantity": 5,
  "notes": "Purchased at store" (optional)
}

Response 201:
{
  "data": {
    "id": "movement-uuid",
    "stock": {
      "id": "stock-uuid",
      "product_id": "...",
      "location_id": "...",
      "quantity": 10
    },
    "type": "ADD",
    "quantity": 5,
    "notes": "Purchased at store",
    "created_at": "2026-01-18T12:00:00Z"
  }
}
```

If no Stock record exists for product+location, it's auto-created with quantity 0 before applying the movement.

### List Stock Levels

For the dedicated Stock page.

```
GET /api/internal/v1/stocks
GET /api/internal/v1/stocks?location={uuid}
GET /api/internal/v1/stocks?low_stock=true
GET /api/internal/v1/stocks?location={uuid}&low_stock=true

Response 200:
{
  "data": [
    {
      "id": "stock-uuid",
      "product": {
        "id": "...",
        "name": "Milk",
        "min_stock": 2
      },
      "location": {
        "id": "...",
        "name": "Fridge"
      },
      "quantity": 5,
      "updated_at": "2026-01-18T12:00:00Z"
    }
  ],
  "meta": { "total": 15 }
}
```

**Filters:**
- `location` - UUID of location to filter by
- `low_stock` - boolean, returns items where quantity < product.minStock

### Product Response Extension

Extend existing product endpoints to include stock summary.

```
GET /api/internal/v1/products/{uuid}

Response includes new field:
{
  "data": {
    "id": "...",
    "name": "Milk",
    ...existing fields...,
    "stock_summary": {
      "total_quantity": 8,
      "locations": [
        { "location_id": "...", "location_name": "Fridge", "quantity": 5 },
        { "location_id": "...", "location_name": "Pantry", "quantity": 3 }
      ]
    }
  }
}
```

## Service Layer

### StockService

```php
class StockService
{
    public function createMovement(CreateStockMovementRequest $request): StockMovement
    public function listStocks(?Uuid $locationId, bool $lowStockOnly): array
    public function getStockSummaryForProduct(Uuid $productId): StockSummary
}
```

### Validation Rules

- `product_id` must exist and be active
- `location_id` must exist
- `quantity` must be positive integer (for ADD/REMOVE) or non-negative (for ADJUST)
- REMOVE cannot result in negative stock

### Exceptions

- `InsufficientStockException` - when REMOVE would result in negative quantity
- Reuse existing: `ProductNotFoundException`, `LocationNotFoundException`

## Files to Create

### Entities
- `src/Entity/Stock.php`
- `src/Entity/StockMovement.php`

### Repositories
- `src/Repository/StockRepository.php`
- `src/Repository/StockMovementRepository.php`

### Service
- `src/Service/StockService.php`

### Request DTOs
- `src/Request/CreateStockMovementRequest.php`

### Response DTOs
- `src/Response/Stock/StockResponse.php`
- `src/Response/Stock/StockMovementResponse.php`
- `src/Response/Stock/StockSummaryResponse.php`

### Controller
- `src/Controller/Api/Internal/V1/StockController.php`

### Exceptions
- `src/Exception/Stock/InsufficientStockException.php`

### Database
- `migrations/VersionXXX.php` - create stock + stock_movements tables

### Factories (Testing)
- `src/Factory/StockFactory.php`
- `src/Factory/StockMovementFactory.php`

### Updates
- `src/Response/Product/ProductResponse.php` - add stock_summary field

## Tests

### Controller Tests
`tests/Controller/Api/Internal/V1/StockControllerTest.php`
- POST /stocks/movements (ADD, REMOVE, ADJUST)
- GET /stocks with filters (location, low_stock)
- Validation errors (invalid product, negative quantity, insufficient stock)
- Auto-creation of Stock record on first movement

### Service Tests
`tests/Service/StockServiceTest.php`
- Movement calculations (ADD increases, REMOVE decreases, ADJUST sets)
- InsufficientStockException on over-removal
- Stock summary aggregation

### Test Scenarios
1. ADD 5 to empty stock → quantity = 5
2. ADD 3 more → quantity = 8
3. REMOVE 2 → quantity = 6
4. REMOVE 10 → throws InsufficientStockException
5. ADJUST to 3 → quantity = 3 (regardless of previous)
6. Same product, different locations → separate stock records
7. lowStock filter → returns only where quantity < minStock

## Future Considerations (Out of Scope)

### Expiry Tracking
- Add `expiry_date` field to Stock or separate StockBatch entity
- Track FIFO (first-in-first-out) consumption
- Expiry alerts and notifications
- "Use by" suggestions

### Movement History Endpoint
- `GET /products/{id}/movements` - view audit log
- Filtering by date range, movement type
- Useful for debugging discrepancies

### Transfer Between Locations
- `TRANSFER` movement type
- Single transaction: REMOVE from source + ADD to destination
- Or dedicated endpoint: `POST /stocks/transfers`

### Batch Operations
- Bulk movements for inventory counts
- Import from spreadsheet
