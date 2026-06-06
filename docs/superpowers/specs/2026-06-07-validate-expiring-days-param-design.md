# Validate the `days` query param on /stocks/expiring (#66)

**Date:** 2026-06-07
**Issue:** #66 · tech-debt
**Severity:** Low · **Effort:** S · **Area:** backend / stock + API validation

## Problem

`StockController::expiring()` reads `days` via
`$request->query->getInt('days', 7)`
(`backend/src/Controller/Api/Internal/V1/StockController.php:144`) with no
validation. A negative value (e.g. `?days=-5`) produces a **past** cutoff and a
narrow/empty result set rather than a `400`/`422`. After #53,
`HouseholdCalendar::expiryCutoff()` handles negatives correctly (no crash), so
this is a silent-bad-input issue, not a fault. `getInt` also coerces non-numeric
junk (`?days=abc`) silently to `0`.

## Goal

Reject `days < 0` with a structured validation error, using the codebase's
existing validation machinery so the behavior is consistent with every other
validated input in the app. The day-math itself (#53) is unchanged.

## Decisions

- **Mechanism: a `#[MapQueryString]` query DTO, not an inline guard.** The app
  has 18 request DTOs validated via `#[Assert]` constraints and surfaced as
  `422 VALIDATION_ERROR` through `ApiExceptionListener`. Crucially, that
  listener already carries a forward-looking comment written for exactly this
  work (`backend/src/EventListener/ApiExceptionListener.php:66-69`): a
  query-string DTO's validation failure is unwrapped and normalized to **422**.
  A query DTO is therefore the low-surprise, convention-matching choice; an
  inline `if/throw` would introduce a second, divergent validation style in a
  controller that otherwise only delegates.

- **Status code: 422, not the issue's suggested 400.** The issue asks for
  behavior "consistent with how other endpoints validate query params" — and
  that convention is `422 VALIDATION_ERROR` with a structured `errors[]` body.
  Honoring the issue's *intent* (consistency) means 422, not its literal "400".

- **Floor: `days >= 0` (`PositiveOrZero`, not `Positive`).** `days=0` is
  meaningful — the cutoff is "today," returning items expiring today plus
  already-expired ones. Only negatives are nonsensical. This matches the issue's
  `days < 0` floor.

- **No upper bound.** The dataset is household-scale stock, naturally bounded by
  how many entries carry a `best_before`. A cap would be an arbitrary magic
  number — YAGNI. Trivial to add later if a real need appears.

## Changes

### Backend

1. **New DTO — `backend/src/Request/ExpiringStockQuery.php`**

   ```php
   <?php

   declare(strict_types = 1);

   namespace App\Request;

   use Symfony\Component\Validator\Constraints as Assert;

   final readonly class ExpiringStockQuery
   {
       public function __construct(
           #[Assert\PositiveOrZero(message: 'Days must be zero or greater.')]
           public int $days = 7,
       ) {
       }
   }
   ```

2. **`backend/src/Controller/Api/Internal/V1/StockController.php`** — bind the
   DTO via `#[MapQueryString]`, drop the manual `getInt`:

   ```php
   public function expiring(
       #[MapQueryString] ExpiringStockQuery $query = new ExpiringStockQuery()
   ): JsonResponse {
       $data = $this->stockEntryService->getExpiringEntries($query->days);

       return $this->json([
           'data' => $data,
           'meta' => ['total' => count($data)],
       ]);
   }
   ```

   The `= new ExpiringStockQuery()` default ensures a bare
   `/stocks/expiring` (no query string) still resolves to the default `days=7`
   rather than failing to bind. Add the
   `Symfony\Component\HttpKernel\Attribute\MapQueryString` import.

3. **OpenAPI annotation.** The endpoint currently declares the `days` parameter
   manually
   (`#[OA\Parameter(name: 'days', in: 'query', ... default: 7)]`). nelmio derives
   query parameters from a `#[MapQueryString]` DTO, so the manual annotation
   becomes redundant and risks duplication. **Remove the manual
   `#[OA\Parameter(name: 'days', ...)]`** and let it derive from the DTO
   (mirroring how `/stocks/add` relies on `new Model(type: AddStockRequest)`
   rather than hand-written body params). Verify the derived parameter — with
   its `minimum: 0` — appears in `/api/doc.json` during verification; if nelmio
   does **not** derive it on this version, restore the manual annotation with
   `minimum: 0` instead.

4. **`backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php`**
   — add cases (mirroring `testAddStockRejectsQuantityAboveLimit`):
   - `GET /stocks/expiring?days=-5` → **422** `VALIDATION_ERROR`
     (via `assertErrorResponse`).
   - `GET /stocks/expiring?days=0` → **200** — proves `0` is allowed and locks
     the floor decision (seed an entry already expired / expiring today and
     assert it is returned).

   The existing `testExpiringReturnsEntriesWithinDays` /
   `testExpiringIncludesAlreadyExpiredItems` already cover the happy path and
   the default; no existing test changes.

## Out of scope (won't do)

- The sibling unvalidated GET query params elsewhere
  (`TaskController` `status`, `ProductController` `name`/`category_id`). The
  issue scopes us to `/stocks/expiring`; a query DTO is now an established
  pattern they can adopt later.
- The timezone/day-math behavior (fixed in #53).
- Any upper bound on `days`.
- Frontend changes — the SPA does not currently send negative `days`.

## Acceptance criteria

- `GET /stocks/expiring?days=-5` returns **422** `VALIDATION_ERROR`.
- `GET /stocks/expiring?days=0` returns **200** and includes today/expired items.
- `GET /stocks/expiring` (no param) and `?days=7` behave exactly as before.
- Backend: `make lint && make test` green.

## Verification

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint && make test

# DTO + binding in place
grep -n "PositiveOrZero" src/Request/ExpiringStockQuery.php
grep -n "MapQueryString" src/Controller/Api/Internal/V1/StockController.php

# OpenAPI: days param still documented with a minimum
# (start the stack, then)
curl -sk https://localhost/api/doc.json | grep -A5 '"days"'
```
