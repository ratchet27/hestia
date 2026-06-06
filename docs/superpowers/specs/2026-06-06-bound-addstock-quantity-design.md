# Bound addStock quantity (W5, #59)

**Date:** 2026-06-06
**Issue:** #59 (W5) · architecture-review, tech-debt
**Review ref:** `docs/reviews/2026-06-05-backend-architecture/README.md` § W5
**Severity:** Low · **Effort:** S

## Problem

Stock is modeled as discrete rows — one `StockEntry` per unit (a deliberate
design per spec §8). `AddStockRequest.quantity` carries only
`#[Assert\Positive]` with no upper bound, so a request like `quantity: 100000`
creates 100k `INSERT`s (and a 100k-row FIFO scan/delete later). On a
single-household home server a fat-finger is a self-inflicted DoS: a bloated
table and slow consume/FIFO afterward.

The frontend `AddStockModal` is also unbounded — it enforces `min="1"` but has
no `max`, so the SPA happily submits any positive integer.

## Goal

Reject fat-fingered mass inserts by capping `addStock` quantity at **50** per
request, enforced at both edges (backend DTO validation + frontend form). The
one-row-per-unit storage model is **not** changed.

## Decisions

- **Cap value: 50.** A generous household restock (a flat of 30 eggs, a case of
  24) still works; a 6-digit fat-finger is rejected.
- **Enforce at both edges.** Backend constraint is the authority; the frontend
  cap is defense-in-depth and gives the user a clear message before the request
  is sent.

## Changes

### Backend

1. **`backend/src/Request/AddStockRequest.php`** — add
   `#[Assert\LessThanOrEqual(value: 50, message: ...)]` alongside the existing
   `#[Assert\Positive]` on `$quantity`, with a clear validation message.

   The `/stocks/add` endpoint maps its body via
   `new Model(type: AddStockRequest::class)`
   (`backend/src/Controller/Api/Internal/V1/StockController.php:154`), so this
   constraint automatically surfaces as `maximum: 50` in the generated OpenAPI
   schema — **no manual `OA` annotation needed.**

2. **`backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php`**
   — boundary tests:
   - `POST /stocks/add` with `quantity: 51` → **422** `VALIDATION_ERROR`
   - `POST /stocks/add` with `quantity: 50` → **201** success

   Existing tests max out at `quantity: 5`, so no existing test needs changing.

### Frontend (defense-in-depth + clearer UX)

3. **`frontend/src/features/stock/components/AddStockModal.tsx`** — add
   `max="50"` to the quantity `<input>` and
   `max: { value: 50, message: t("addStock.quantityMax") }` to the
   `register("quantity", …)` rules, mirroring the existing `min` pattern
   (`AddStockModal.tsx:146-149`).

4. **i18n** — add a new key `addStock.quantityMax`:
   - `frontend/src/i18n/locales/en.json` → `"Maximum 50"`
   - `frontend/src/i18n/locales/ru.json` → `"Максимум 50"`

5. **`frontend/src/features/stock/components/AddStockModal.test.tsx`** — entering
   `51` shows the max error and blocks submit; `50` is accepted.

6. **Regenerate API client** —
   `NODE_TLS_REJECT_UNAUTHORIZED=0 bun run generate-api` so
   `frontend/src/api/generated/models/addStockRequest.ts` picks up the
   `maximum: 50` JSDoc. This is type/doc-only churn; the real runtime
   enforcement is the react-hook-form `max` rule and the backend constraint.

## Out of scope (won't do)

- Changing the discrete-entry storage model (deliberate per spec §8).
- Bulk-insert optimization.
- The consume path (already bounded by availability).
- Broader rate limiting / abuse protection.

## Acceptance criteria

- `AddStockRequest.quantity` rejects values above 50 with a 422; values `1..50`
  still work.
- The frontend blocks `> 50` with a localized message before submitting.
- `maximum: 50` is present in the generated OpenAPI schema / client model.
- Backend: `make lint && make test` green.
- Frontend: `bun run check && bun run test:run` green.

## Verification

```bash
# backend
cd /home/pavel/projects/personal/hestia/backend
make lint && make test
grep -n "LessThanOrEqual" src/Request/AddStockRequest.php

# frontend
cd /home/pavel/projects/personal/hestia/frontend
bun run check && bun run test:run
grep -n "quantityMax" src/i18n/locales/en.json src/i18n/locales/ru.json
```
