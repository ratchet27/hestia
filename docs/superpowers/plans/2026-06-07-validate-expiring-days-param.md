# Validate `days` Query Param on /stocks/expiring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reject `days < 0` on `GET /stocks/expiring` with a `422 VALIDATION_ERROR`, using a `#[MapQueryString]` query DTO so behavior matches every other validated input in the app.

**Architecture:** Introduce a small `ExpiringStockQuery` request DTO carrying a single `#[Assert\PositiveOrZero]` `days` field (default 7). Bind it on the controller action via `#[MapQueryString]`, replacing the unvalidated `$request->query->getInt('days', 7)`. The existing `ApiExceptionListener` already unwraps query-DTO validation failures and normalizes them to 422 — no listener change needed.

**Tech Stack:** PHP 8.4, Symfony, symfony/validator, PHPUnit functional tests (Docker — `docker compose exec php`), `make lint` / `make test`.

**Spec:** `docs/superpowers/specs/2026-06-07-validate-expiring-days-param-design.md`

---

## File Structure

- **Create:** `backend/src/Request/ExpiringStockQuery.php` — query DTO, single responsibility: declare + validate the `days` query parameter. Mirrors the other 18 DTOs in `backend/src/Request/`.
- **Modify:** `backend/src/Controller/Api/Internal/V1/StockController.php` — `expiring()` action: bind the DTO, drop `getInt`, drop the now-redundant manual `#[OA\Parameter(name: 'days', ...)]`, add the `MapQueryString` import.
- **Modify (test):** `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php` — add a negative-days rejection test and a zero-days acceptance test.

All commands run from `/home/pavel/projects/personal/hestia/backend`. PHP/PHPUnit run **inside Docker** (`docker compose exec php …`); `make lint` runs on the host.

---

### Task 1: Reject negative `days` with 422

**Files:**
- Create: `backend/src/Request/ExpiringStockQuery.php`
- Modify: `backend/src/Controller/Api/Internal/V1/StockController.php` (the `expiring()` action, `:124-151`, and imports)
- Test: `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php`

- [ ] **Step 1: Write the failing test**

Add this method to `StockControllerTest` (place it next to the other expiring tests, after `testExpiringIncludesAlreadyExpiredItems`):

```php
    public function testExpiringRejectsNegativeDays(): void
    {
        $response = $this->apiGet('/stocks/expiring', ['days' => '-5']);
        $data = static::assertErrorResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);

        static::assertSame('VALIDATION_ERROR', $data['type']);
        static::assertNotEmpty($data['errors']);
        static::assertSame('days', $data['errors'][0]['property']);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run:
```bash
docker compose exec php bin/phpunit --filter testExpiringRejectsNegativeDays
```
Expected: **FAIL** — current code uses `getInt('days', 7)`, so `?days=-5` returns `200 OK` (a past cutoff), not 422. The `assertErrorResponse(..., 422)` assertion fails on the `200` status.

- [ ] **Step 3: Create the query DTO**

Create `backend/src/Request/ExpiringStockQuery.php`:

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

- [ ] **Step 4: Wire the DTO into the controller**

In `backend/src/Controller/Api/Internal/V1/StockController.php`:

(a) Add the imports (alongside the existing `use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;`):
```php
use App\Request\ExpiringStockQuery;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
```

(b) Remove the manual `days` parameter annotation from the `expiring()` action's attributes — delete this line:
```php
    #[OA\Parameter(name: 'days', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 7))]
```

(c) Replace the `expiring()` method body and signature. Change from:
```php
    public function expiring(Request $request): JsonResponse
    {
        $days = $request->query->getInt('days', 7);
        $data = $this->stockEntryService->getExpiringEntries($days);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }
```
to:
```php
    public function expiring(
        #[MapQueryString] ExpiringStockQuery $query = new ExpiringStockQuery()
    ): JsonResponse {
        $data = $this->stockEntryService->getExpiringEntries($query->days);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }
```

The `= new ExpiringStockQuery()` default makes a bare `/stocks/expiring` (empty query string) resolve to `days=7` instead of failing to bind.

> Note: do **not** remove the `use Symfony\Component\HttpFoundation\Request;` import — other actions in this controller (`summary`, `listEntries`) still type-hint `Request`.

- [ ] **Step 5: Run the test to verify it passes**

Run:
```bash
docker compose exec php bin/phpunit --filter testExpiringRejectsNegativeDays
```
Expected: **PASS**.

> If `$data['errors'][0]['property']` is not `'days'` (MapQueryString property-path naming differs across Symfony versions), keep the `type` + `assertNotEmpty($data['errors'])` assertions and adjust/drop the property assertion to match the actual path — the 422 status is the contract that matters.

- [ ] **Step 6: Commit**

```bash
git add backend/src/Request/ExpiringStockQuery.php \
        backend/src/Controller/Api/Internal/V1/StockController.php \
        backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php
git commit -s -m "feat(stock): validate days query param on /stocks/expiring (#66)"
```

---

### Task 2: Lock the floor — `days=0` is allowed (returns today/expired)

**Files:**
- Test: `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php`

This test passes immediately after Task 1 (the constraint is `PositiveOrZero`, not `Positive`). It is a **regression lock** for the floor decision — it fails if anyone later tightens the constraint to `Positive`, which would wrongly reject the meaningful `days=0` ("today + already expired") query.

- [ ] **Step 1: Write the test**

Add to `StockControllerTest`, next to the other expiring tests:

```php
    public function testExpiringAllowsZeroDays(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Already expired (yesterday) — must be included by a days=0 cutoff of "today".
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable('yesterday')
        ]);

        $response = $this->apiGet('/stocks/expiring', ['days' => '0']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
    }
```

- [ ] **Step 2: Run the test to verify it passes**

Run:
```bash
docker compose exec php bin/phpunit --filter testExpiringAllowsZeroDays
```
Expected: **PASS** (status 200, one already-expired entry returned). If it FAILS with 422, the constraint was set to `Positive` instead of `PositiveOrZero` — fix the DTO.

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php
git commit -s -m "test(stock): lock days=0 as a valid expiring cutoff (#66)"
```

---

### Task 3: Verify OpenAPI derivation and run the full gate

**Files:** none changed unless the OpenAPI fallback is needed.

- [ ] **Step 1: Run the full backend gate**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
docker compose exec php bin/phpunit --filter StockControllerTest
```
Expected: `make lint` green (rector → mago format → mago lint → mago analyze → phpstan); all `StockControllerTest` tests pass.

> `make lint` may rewrite files (rector/format). Stage explicitly — never `git add -A`. If it reformats `ExpiringStockQuery.php` or `StockController.php`, `git add` those specific files and amend or add a `style` commit.

- [ ] **Step 2: Verify the `days` parameter is still documented**

The manual `#[OA\Parameter]` was removed; confirm nelmio derives it from the DTO. With the stack running:
```bash
curl -sk https://localhost/api/doc.json | python3 -m json.tool | grep -A8 '"/api/internal/v1/stocks/expiring"' | grep -i 'days\|minimum'
```
Expected: a `days` query parameter appears, ideally with `minimum: 0`.

**Fallback (only if `days` is NOT in the derived doc):** restore an explicit annotation on the `expiring()` action with the floor documented, and commit it:
```php
    #[OA\Parameter(name: 'days', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 7, minimum: 0))]
```
```bash
git add backend/src/Controller/Api/Internal/V1/StockController.php
git commit -s -m "docs(stock): document days param minimum on /stocks/expiring (#66)"
```

- [ ] **Step 3: Final confirmation**

Confirm all acceptance criteria from the spec:
- `?days=-5` → 422 `VALIDATION_ERROR` (Task 1 test).
- `?days=0` → 200 with today/expired items (Task 2 test).
- `?days=7` and no-param → unchanged (existing `testExpiringReturnsEntriesWithinDays` / `testExpiringIncludesAlreadyExpiredItems` still pass).
- `make lint && make test` green.

---

## Notes

- **Out of scope:** sibling unvalidated GET params (`TaskController` `status`, `ProductController` `name`/`category_id`), the day-math itself (#53), any upper bound on `days`, frontend changes.
- **No frontend / API client regeneration needed** — this only tightens server-side acceptance of an existing param; the generated client's `days` type (`number`) is unchanged.
