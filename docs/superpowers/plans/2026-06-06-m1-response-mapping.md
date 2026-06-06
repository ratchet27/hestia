# M1 — Unify Response-Mapping Convention Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Document one response-mapping convention and remove the inline-array wart, by giving the Stock-entry DTOs co-located pure `fromEntity()` factories and converting `StockController::add` to a real DTO — with zero API JSON shape changes.

**Architecture:** Three legitimate mapping categories — ObjectMapper `#[Map]` (flat), static `DTO::fromEntity(Entity, …scalars)` factory (computed fields), and service-assembly (aggregates). Factories stay **pure**: the service computes derived scalars (e.g. `days_until_expiry` via `HouseholdCalendar`) and passes them in, so factories are unit-testable with no container and no mocks. See spec: `docs/superpowers/specs/2026-06-06-m1-response-mapping-design.md`.

**Tech Stack:** PHP 8.x, Symfony, Doctrine, PHPUnit (Docker), Mago + PHPStan + Rector (`make lint`), frontend orval client generation (`bun`).

**Branch:** `refactor/m1-response-mapping` (already created).

**Conventions for every task below:**
- All PHP files start with `<?php\n\ndeclare(strict_types = 1);` (note the spaces around `=` — Mago enforces this).
- Run a single test with: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter=<TestClassOrMethod>`
- Full backend gate: `cd /home/pavel/projects/personal/hestia/backend && make lint && make test`
- Entities use a no-arg constructor + fluent setters; `Product`/`Location`/`StockEntry` all set `$this->id = Uuid::v7()` in their constructor, so `getId()` is safe immediately in unit tests.
- Stage files explicitly (`git add <paths>`) — never `git add -A` (Mago/Rector rewrite files during `make lint`).

---

## File Structure

| File | Responsibility | Action |
|------|----------------|--------|
| `backend/src/Response/Stock/ProductBriefResponse.php` | Brief product projection + `fromEntity` | Modify (add factory) |
| `backend/src/Response/Stock/AddedStockEntryResponse.php` | `{id, best_before}` returned by `add` | **Create** |
| `backend/src/Response/Stock/StockEntryResponse.php` | Full stock entry DTO + `fromEntity` | Modify (add factory) |
| `backend/src/Response/Stock/ExpiringEntryResponse.php` | Expiring entry DTO + `fromEntity` | Modify (add factory) |
| `backend/src/Service/StockEntryService.php` | Computes scalars, calls factories; no inline mapping | Modify |
| `backend/src/Controller/Api/Internal/V1/StockController.php` | `add` returns DTOs; OpenAPI annotation corrected | Modify |
| `backend/tests/Unit/Response/Stock/*Test.php` | Pure unit tests for the 4 factories | **Create** |
| `backend/AGENTS.md` | Document the convention | Modify |
| `frontend/src/api/generated/**` | Regenerated client matching real `add` response | Regenerate |

---

## Task 1: `ProductBriefResponse::fromEntity()`

This is the pure base factory reused by every other Stock factory and the summary builder (dedupes a 3× hand-build).

**Files:**
- Create: `backend/tests/Unit/Response/Stock/ProductBriefResponseTest.php`
- Modify: `backend/src/Response/Stock/ProductBriefResponse.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Response/Stock/ProductBriefResponseTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Response\Stock;

use App\Entity\Product;
use App\Response\Stock\ProductBriefResponse;
use PHPUnit\Framework\TestCase;

final class ProductBriefResponseTest extends TestCase
{
    public function testFromEntityCopiesIdNameAndUnit(): void
    {
        $product = (new Product())->setName('Milk')->setUnit('pcs');

        $response = ProductBriefResponse::fromEntity($product);

        static::assertSame($product->getId(), $response->id);
        static::assertSame('Milk', $response->name);
        static::assertSame('pcs', $response->unit);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter=ProductBriefResponseTest`
Expected: FAIL — `Call to undefined method App\Response\Stock\ProductBriefResponse::fromEntity()`.

- [ ] **Step 3: Add the factory**

Edit `backend/src/Response/Stock/ProductBriefResponse.php`. Add the `Product` import and the static method. Final file:

```php
<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Entity\Product;
use Symfony\Component\Uid\Uuid;

final readonly class ProductBriefResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public string $unit
    ) {
    }

    public static function fromEntity(Product $product): self
    {
        return new self(
            id: $product->getId(),
            name: $product->getName(),
            unit: $product->getUnit()
        );
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter=ProductBriefResponseTest`
Expected: PASS (1 test, 3 assertions).

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add src/Response/Stock/ProductBriefResponse.php tests/Unit/Response/Stock/ProductBriefResponseTest.php
git commit -s -m "refactor(stock): add ProductBriefResponse::fromEntity factory (M1, #60)"
```

---

## Task 2: `AddedStockEntryResponse` DTO (new) — the `add` response shape

A minimal DTO matching the **real** `add` response (`{id, best_before}`).

**Files:**
- Create: `backend/tests/Unit/Response/Stock/AddedStockEntryResponseTest.php`
- Create: `backend/src/Response/Stock/AddedStockEntryResponse.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Response/Stock/AddedStockEntryResponseTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Response\Stock;

use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Response\Stock\AddedStockEntryResponse;
use PHPUnit\Framework\TestCase;

final class AddedStockEntryResponseTest extends TestCase
{
    public function testFromEntityMapsIdAndFormatsDate(): void
    {
        $entry = (new StockEntry())
            ->setProduct((new Product())->setName('Milk')->setUnit('pcs'))
            ->setLocation((new Location())->setName('Fridge'))
            ->setBestBefore(new \DateTimeImmutable('2026-06-10 14:30:00'));

        $response = AddedStockEntryResponse::fromEntity($entry);

        static::assertSame($entry->getId(), $response->id);
        static::assertSame('2026-06-10', $response->best_before);
    }

    public function testFromEntityKeepsNullBestBefore(): void
    {
        $entry = (new StockEntry())
            ->setProduct((new Product())->setName('Milk')->setUnit('pcs'))
            ->setLocation((new Location())->setName('Pantry'));

        $response = AddedStockEntryResponse::fromEntity($entry);

        static::assertNull($response->best_before);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter=AddedStockEntryResponseTest`
Expected: FAIL — class `App\Response\Stock\AddedStockEntryResponse` not found.

- [ ] **Step 3: Create the DTO**

Create `backend/src/Response/Stock/AddedStockEntryResponse.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Entity\StockEntry;
use Symfony\Component\Uid\Uuid;

final readonly class AddedStockEntryResponse
{
    public function __construct(
        public Uuid $id,
        public ?string $best_before
    ) {
    }

    public static function fromEntity(StockEntry $entry): self
    {
        return new self(
            id: $entry->getId(),
            best_before: $entry->getBestBefore()?->format('Y-m-d')
        );
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter=AddedStockEntryResponseTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add src/Response/Stock/AddedStockEntryResponse.php tests/Unit/Response/Stock/AddedStockEntryResponseTest.php
git commit -s -m "refactor(stock): add AddedStockEntryResponse DTO for add endpoint (M1, #60)"
```

---

## Task 3: `StockEntryResponse::fromEntity()`

Pure factory taking the entity + a pre-computed `?int $daysUntilExpiry`. Uses `ProductBriefResponse::fromEntity` and builds the nested `LocationResponse` by constructor (composition). `best_before` stays `Y-m-d`; `created_at` stays a `DateTimeImmutable` (serialized as ATOM by the normalizer).

**Files:**
- Create: `backend/tests/Unit/Response/Stock/StockEntryResponseTest.php`
- Modify: `backend/src/Response/Stock/StockEntryResponse.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Response/Stock/StockEntryResponseTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Response\Stock;

use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Response\Stock\StockEntryResponse;
use PHPUnit\Framework\TestCase;

final class StockEntryResponseTest extends TestCase
{
    public function testFromEntityMapsFieldsAndFormatsBestBeforeAsDate(): void
    {
        $entry = (new StockEntry())
            ->setProduct((new Product())->setName('Milk')->setUnit('pcs'))
            ->setLocation((new Location())->setName('Fridge'))
            ->setBestBefore(new \DateTimeImmutable('2026-06-10 14:30:00'));

        $response = StockEntryResponse::fromEntity($entry, 4);

        static::assertSame($entry->getId(), $response->id);
        static::assertSame('Milk', $response->product->name);
        static::assertSame('Fridge', $response->location->name);
        static::assertSame('2026-06-10', $response->best_before);
        static::assertSame($entry->getCreatedAt(), $response->created_at);
        static::assertSame(4, $response->days_until_expiry);
    }

    public function testFromEntityKeepsNullBestBeforeAndNullDays(): void
    {
        $entry = (new StockEntry())
            ->setProduct((new Product())->setName('Milk')->setUnit('pcs'))
            ->setLocation((new Location())->setName('Pantry'));

        $response = StockEntryResponse::fromEntity($entry, null);

        static::assertNull($response->best_before);
        static::assertNull($response->days_until_expiry);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter=StockEntryResponseTest`
Expected: FAIL — undefined method `StockEntryResponse::fromEntity()`.

- [ ] **Step 3: Add the factory**

Edit `backend/src/Response/Stock/StockEntryResponse.php`. Add the `StockEntry` import and the static method. Final file:

```php
<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Entity\StockEntry;
use App\Response\Location\LocationResponse;
use Symfony\Component\Uid\Uuid;

// @mago-ignore lint:excessive-parameter-list
final readonly class StockEntryResponse
{
    public function __construct(
        public Uuid $id,
        public ProductBriefResponse $product,
        public LocationResponse $location,
        public ?string $best_before,
        public \DateTimeImmutable $created_at,
        public ?int $days_until_expiry
    ) {
    }

    public static function fromEntity(StockEntry $entry, ?int $daysUntilExpiry): self
    {
        return new self(
            id: $entry->getId(),
            product: ProductBriefResponse::fromEntity($entry->getProduct()),
            location: new LocationResponse(
                id: $entry->getLocation()->getId(),
                name: $entry->getLocation()->getName()
            ),
            best_before: $entry->getBestBefore()?->format('Y-m-d'),
            created_at: $entry->getCreatedAt(),
            days_until_expiry: $daysUntilExpiry
        );
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter=StockEntryResponseTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add src/Response/Stock/StockEntryResponse.php tests/Unit/Response/Stock/StockEntryResponseTest.php
git commit -s -m "refactor(stock): add StockEntryResponse::fromEntity factory (M1, #60)"
```

---

## Task 4: `ExpiringEntryResponse::fromEntity()`

Same shape, but `best_before` and `days_until_expiry` are **non-null** (the `findExpiring` query guarantees a `best_before`). The `@var` annotation mirrors the existing service closure to satisfy PHPStan.

**Files:**
- Create: `backend/tests/Unit/Response/Stock/ExpiringEntryResponseTest.php`
- Modify: `backend/src/Response/Stock/ExpiringEntryResponse.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Response/Stock/ExpiringEntryResponseTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Response\Stock;

use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Response\Stock\ExpiringEntryResponse;
use PHPUnit\Framework\TestCase;

final class ExpiringEntryResponseTest extends TestCase
{
    public function testFromEntityMapsFieldsWithNonNullBestBefore(): void
    {
        $entry = (new StockEntry())
            ->setProduct((new Product())->setName('Yogurt')->setUnit('pcs'))
            ->setLocation((new Location())->setName('Fridge'))
            ->setBestBefore(new \DateTimeImmutable('2026-06-07'));

        $response = ExpiringEntryResponse::fromEntity($entry, 1);

        static::assertSame('2026-06-07', $response->best_before);
        static::assertSame(1, $response->days_until_expiry);
        static::assertSame('Yogurt', $response->product->name);
        static::assertSame('Fridge', $response->location->name);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter=ExpiringEntryResponseTest`
Expected: FAIL — undefined method `ExpiringEntryResponse::fromEntity()`.

- [ ] **Step 3: Add the factory**

Edit `backend/src/Response/Stock/ExpiringEntryResponse.php`. Final file:

```php
<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Entity\StockEntry;
use App\Response\Location\LocationResponse;
use Symfony\Component\Uid\Uuid;

final readonly class ExpiringEntryResponse
{
    public function __construct(
        public Uuid $id,
        public ProductBriefResponse $product,
        public LocationResponse $location,
        public string $best_before,
        public int $days_until_expiry
    ) {
    }

    public static function fromEntity(StockEntry $entry, int $daysUntilExpiry): self
    {
        /** @var \DateTimeImmutable $bestBefore - guaranteed non-null by the findExpiring query */
        $bestBefore = $entry->getBestBefore();

        return new self(
            id: $entry->getId(),
            product: ProductBriefResponse::fromEntity($entry->getProduct()),
            location: new LocationResponse(
                id: $entry->getLocation()->getId(),
                name: $entry->getLocation()->getName()
            ),
            best_before: $bestBefore->format('Y-m-d'),
            days_until_expiry: $daysUntilExpiry
        );
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter=ExpiringEntryResponseTest`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add src/Response/Stock/ExpiringEntryResponse.php tests/Unit/Response/Stock/ExpiringEntryResponseTest.php
git commit -s -m "refactor(stock): add ExpiringEntryResponse::fromEntity factory (M1, #60)"
```

---

## Task 5: Refactor `StockEntryService` to use the factories

Remove the private `mapEntryToResponse`, replace it with a thin `daysUntilExpiry()` scalar helper, route mapping through the factories, and dedupe the `ProductBriefResponse` hand-build inside `getStockSummary`. Guarded by the existing functional tests (no JSON shape change).

**Files:**
- Modify: `backend/src/Service/StockEntryService.php`
- Test (existing, no change): `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php`

- [ ] **Step 1: Replace the private mapper with a scalar helper**

In `backend/src/Service/StockEntryService.php`, replace the entire private method `mapEntryToResponse` (currently lines ~365-381):

```php
    private function mapEntryToResponse(StockEntry $entry): StockEntryResponse
    {
        $bestBefore = $entry->getBestBefore();

        return new StockEntryResponse(
            id: $entry->getId(),
            product: new ProductBriefResponse(
                id: $entry->getProduct()->getId(),
                name: $entry->getProduct()->getName(),
                unit: $entry->getProduct()->getUnit()
            ),
            location: new LocationResponse(id: $entry->getLocation()->getId(), name: $entry->getLocation()->getName()),
            best_before: $bestBefore?->format('Y-m-d'),
            created_at: $entry->getCreatedAt(),
            days_until_expiry: $bestBefore !== null ? $this->householdCalendar->daysUntil($bestBefore) : null
        );
    }
```

with:

```php
    private function daysUntilExpiry(StockEntry $entry): ?int
    {
        $bestBefore = $entry->getBestBefore();

        return $bestBefore !== null ? $this->householdCalendar->daysUntil($bestBefore) : null;
    }
```

- [ ] **Step 2: Update `getEntries` to call the factory**

Replace (currently line ~279):

```php
        return array_map($this->mapEntryToResponse(...), $entries);
```

with:

```php
        return array_map(
            fn(StockEntry $entry): StockEntryResponse => StockEntryResponse::fromEntity($entry, $this->daysUntilExpiry($entry)),
            $entries
        );
```

- [ ] **Step 3: Update `getEntry` to call the factory**

Replace (currently line ~292):

```php
        return $this->mapEntryToResponse($entry);
```

with:

```php
        return StockEntryResponse::fromEntity($entry, $this->daysUntilExpiry($entry));
```

- [ ] **Step 4: Update `getExpiringEntries` to call the factory**

Replace the `array_map` closure body (currently lines ~304-325):

```php
        return array_map(
            function (StockEntry $entry): ExpiringEntryResponse {
                /** @var \DateTimeImmutable $bestBefore - guaranteed non-null by findExpiring query */
                $bestBefore = $entry->getBestBefore();

                return new ExpiringEntryResponse(
                    id: $entry->getId(),
                    product: new ProductBriefResponse(
                        id: $entry->getProduct()->getId(),
                        name: $entry->getProduct()->getName(),
                        unit: $entry->getProduct()->getUnit()
                    ),
                    location: new LocationResponse(
                        id: $entry->getLocation()->getId(),
                        name: $entry->getLocation()->getName()
                    ),
                    best_before: $bestBefore->format('Y-m-d'),
                    days_until_expiry: $this->householdCalendar->daysUntil($bestBefore)
                );
            },
            $entries
        );
```

with:

```php
        return array_map(
            function (StockEntry $entry): ExpiringEntryResponse {
                /** @var \DateTimeImmutable $bestBefore - guaranteed non-null by findExpiring query */
                $bestBefore = $entry->getBestBefore();

                return ExpiringEntryResponse::fromEntity($entry, $this->householdCalendar->daysUntil($bestBefore));
            },
            $entries
        );
```

- [ ] **Step 5: Dedupe the `ProductBriefResponse` build inside `getStockSummary`**

In `getStockSummary` (the aggregate builder — stays in the service), replace (currently lines ~250-254):

```php
                product: new ProductBriefResponse(
                    id: $product->getId(),
                    name: $product->getName(),
                    unit: $product->getUnit()
                ),
```

with:

```php
                product: ProductBriefResponse::fromEntity($product),
```

- [ ] **Step 6: Run lint to fix imports and verify static analysis**

`make lint` (Rector + Mago) will drop the now-unused `use App\Response\Location\LocationResponse;` import (the service no longer constructs `LocationResponse` directly) and reformat. `ProductBriefResponse`, `StockEntryResponse`, `ExpiringEntryResponse` imports remain in use.

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: green (Rector/Mago may rewrite the file; that's expected).

- [ ] **Step 7: Run the existing Stock functional tests (shape guard)**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter=StockControllerTest`
Expected: PASS — identical JSON proves no shape change from the refactor.

- [ ] **Step 8: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add src/Service/StockEntryService.php
git commit -s -m "refactor(stock): route entry mapping through fromEntity factories (M1, #60)"
```

---

## Task 6: Fix the wart — `StockController::add` returns a DTO + correct OpenAPI

`add` currently returns a raw `{id, best_before}` array, and its OpenAPI annotation falsely claims a full `StockEntryResponse`. Return `AddedStockEntryResponse` (same JSON) and fix the annotation. The other three `StockEntryResponse` annotation refs (lines 81, 113, 211) are correct and stay.

**Files:**
- Modify: `backend/src/Controller/Api/Internal/V1/StockController.php`
- Test (existing, no change): `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php`

- [ ] **Step 1: Add the import**

In `backend/src/Controller/Api/Internal/V1/StockController.php`, add to the `use` block (keep alphabetical order, before `ConsumeResultResponse`):

```php
use App\Response\Stock\AddedStockEntryResponse;
```

- [ ] **Step 2: Correct the `add` OpenAPI annotation**

Replace (currently line ~163, inside the `add` `#[OA\Response(response: 201 ...)]` block):

```php
                    items: new OA\Items(ref: new Model(type: StockEntryResponse::class))
```

with:

```php
                    items: new OA\Items(ref: new Model(type: AddedStockEntryResponse::class))
```

(Leave the identical-looking lines 81, 113, 211 untouched — those describe `list`/`get`/`update`, which genuinely return `StockEntryResponse`.)

- [ ] **Step 3: Convert the inline array to DTOs**

Replace the `add` method body return (currently lines ~175-183):

```php
        return $this->json([
            'data' => [
                'created' => count($entries),
                'entries' => array_map(static fn($e) => [
                    'id' => $e->getId(),
                    'best_before' => $e->getBestBefore()?->format('Y-m-d')
                ], $entries)
            ]
        ], Response::HTTP_CREATED);
```

with:

```php
        return $this->json([
            'data' => [
                'created' => count($entries),
                'entries' => array_map(AddedStockEntryResponse::fromEntity(...), $entries)
            ]
        ], Response::HTTP_CREATED);
```

- [ ] **Step 4: Run the existing add functional test (shape guard)**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec php bin/phpunit --filter=StockControllerTest`
Expected: PASS — `data.entries[].id` and `data.entries[].best_before` unchanged; no other fields appear.

- [ ] **Step 5: Lint**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: green.

- [ ] **Step 6: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add src/Controller/Api/Internal/V1/StockController.php
git commit -s -m "fix(stock): return AddedStockEntryResponse DTO from add, correct OpenAPI (M1, #60)"
```

---

## Task 7: Regenerate the frontend client & verify the contract correction

The corrected OpenAPI changes the `add` response model from `StockEntryResponse` (wrong) to `AddedStockEntryResponse` (`{id, best_before}` — matches reality). Verify the SPA never depended on the wrong-richer type, then regenerate.

**Files:**
- Regenerate: `frontend/src/api/generated/**`
- Verify (read-only): the frontend `addStock` call site(s)

- [ ] **Step 1: Confirm the SPA doesn't read fields beyond `id`/`best_before` from the add response**

Run:
```bash
cd /home/pavel/projects/personal/hestia/frontend
grep -rn "addStock\|stocks/add\|StocksAdd" src/ | grep -v generated
```
Expected: locate the call site. Read it and confirm it does not access `.product`, `.location`, `.days_until_expiry`, or `.created_at` on the returned entries. (It cannot — the runtime never returned them — but confirm visually.) If it does access such a field, STOP and report: that code is already broken against the live API and needs a separate fix.

- [ ] **Step 2: Ensure the backend is running, then regenerate the client**

The generator fetches the live OpenAPI doc, so the backend must be up (`docker compose up -d` in `backend/` if needed).

Run:
```bash
cd /home/pavel/projects/personal/hestia/frontend
NODE_TLS_REJECT_UNAUTHORIZED=0 bun run generate-api
```
Expected: a new model file (e.g. `src/api/generated/models/addedStockEntryResponse.ts`) and the `add` operation's response type now referencing it; the spurious richer type drops off the add response.

- [ ] **Step 3: Frontend gate**

Run: `cd /home/pavel/projects/personal/hestia/frontend && bun run check`
Expected: green (type-check passes — confirms no SPA code relied on the removed fields).

- [ ] **Step 4: Commit**

```bash
cd /home/pavel/projects/personal/hestia/frontend
git add src/api/generated
git commit -s -m "chore(api): regenerate client for corrected addStock response (M1, #60)"
```

---

## Task 8: Document the convention in `backend/AGENTS.md`

**Files:**
- Modify: `backend/AGENTS.md`

- [ ] **Step 1: Add a "Response mapping" subsection**

Open `backend/AGENTS.md`, find the conventions area (near the Controller/Service/Entity conventions). Add this subsection (adjust the surrounding heading level to match the file):

```markdown
### Response mapping

Turn entities into response DTOs using exactly one of these, by case:

1. **Flat entity → DTO, no computed fields:** Symfony ObjectMapper `#[Map]` on the
   DTO (e.g. `CategoryResponse`, `LocationResponse`, `TaskResponse`, `ProductResponse`).
2. **Entity → DTO with computed/derived fields:** a pure static factory
   `DTO::fromEntity(Entity $e, …scalars): self` on the DTO (e.g.
   `StockEntryResponse`, `ExpiringEntryResponse`, `ShoppingItemResponse`). Keep the
   factory free of services — if a value needs a collaborator (e.g.
   `days_until_expiry` needs `HouseholdCalendar`), the **service** computes the
   scalar and passes it in. This keeps factories unit-testable with no container.
3. **DTO assembled from queries / aggregates / multiple sources** (no single source
   entity): build it in the **service** (e.g. `StockSummaryResponse`,
   `ProductSummaryResponse`). No `#[Map]`, no `fromEntity`.

**Never** build a response array inline in a controller — controllers return DTOs.
Constructing a nested DTO via its constructor inside a factory/service is composition,
not a fourth mechanism.

Date formatting: `DateTimeImmutable` fields serialize as ISO-8601/ATOM via
`DateTimeImmutableNormalizer`; date-only fields (e.g. `best_before`) are formatted
`Y-m-d` in the factory/service. Keep that distinction.

Opportunistic migration (not yet converted): `RecipeService::toResponse`,
`CategoryListItemResponse` / `LocationListItemResponse` controller `toResponse()`
methods.
```

- [ ] **Step 2: Lint (AGENTS.md is docs; ensure nothing else regressed)**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: green.

- [ ] **Step 3: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add AGENTS.md
git commit -s -m "docs(backend): document response-mapping convention (M1, #60)"
```

---

## Task 9: Final verification gate

**Files:** none (verification only).

- [ ] **Step 1: Full backend gate**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint && make test`
Expected: both green.

- [ ] **Step 2: Confirm the wart is gone and factories exist**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend
grep -n "'id' =>" src/Controller/Api/Internal/V1/StockController.php   # expect: no match
grep -rn "public static function fromEntity" src/Response/Stock/        # expect: 4 matches
```
Expected: no inline `'id' =>` in the controller; four `fromEntity` factories under `src/Response/Stock/`.

- [ ] **Step 3: Frontend gate**

Run: `cd /home/pavel/projects/personal/hestia/frontend && bun run check`
Expected: green.

- [ ] **Step 4: Acceptance review against the spec**

Confirm each acceptance criterion in `docs/superpowers/specs/2026-06-06-m1-response-mapping-design.md`:
- Convention documented in `AGENTS.md` ✓ (Task 8)
- No inline response arrays in controllers ✓ (Task 6 + grep)
- Stock-entry maps use co-located factories; `mapEntryToResponse` gone ✓ (Tasks 3–5)
- Aggregate DTOs unchanged ✓ (only the nested brief deduped)
- No API JSON shape changes ✓ (functional tests green)
- OpenAPI matches the real `add` response; client regenerated; `bun run check` green ✓ (Tasks 6–7)

- [ ] **Step 5: Push and open the PR** (when ready)

```bash
cd /home/pavel/projects/personal/hestia
gh auth switch -u ratchet27
git push -u origin refactor/m1-response-mapping
gh pr create --fill
```
In the PR description, list the opportunistic outliers left for later (so M1's "incremental, not big-bang" intent is recorded): `RecipeService::toResponse`, `CategoryListItemResponse`/`LocationListItemResponse` controller `toResponse()` methods.

---

## Self-Review

- **Spec coverage:** convention doc (Task 8), Stock factories incl. computed-field handling (Tasks 1,3,4), aggregate boundary respected — only the nested brief deduped, builders untouched (Task 5), wart fix + OpenAPI correction (Task 6), frontend regen + verification gate (Task 7), unit tests for every factory (Tasks 1–4), functional-test shape guard (Tasks 5–6), `Y-m-d`/ATOM preserved (Tasks 3,4,8). All spec acceptance criteria map to Task 9 Step 4.
- **Placeholder scan:** none — every code/edit step shows full code; every command shows expected output.
- **Type consistency:** `fromEntity` signatures are consistent across plan and callers — `ProductBriefResponse::fromEntity(Product)`, `StockEntryResponse::fromEntity(StockEntry, ?int)`, `ExpiringEntryResponse::fromEntity(StockEntry, int)`, `AddedStockEntryResponse::fromEntity(StockEntry)`; service helper `daysUntilExpiry(StockEntry): ?int`; controller uses `AddedStockEntryResponse::fromEntity(...)`.
