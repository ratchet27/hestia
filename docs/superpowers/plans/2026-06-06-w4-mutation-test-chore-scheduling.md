# W4 — Mutation-test the Chore scheduling math (#57) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Widen Infection mutation testing to the logic-bearing entities, pin the genuinely-killable Chore/ShoppingListItem mutants, fix the `Product::removeBarcode` dead code, and add an honest local MSI floor.

**Architecture:** Add `src/Entity` to Infection's source while excluding pure data-holder entities in config (centralized, not per-class annotations). Pin the two real test gaps the baseline probe surfaced — the midnight-normalization contract on `Chore::nextDueAt` and the `ShoppingListItem::getDisplayName` fallback chain — and remove the unkillable no-op block in `Product::removeBarcode`. Set `minCoveredMsi` to the measured floor so regressions are visible on local `make mutate` (CI does not run Infection).

**Tech Stack:** PHP 8.x, Symfony, Doctrine ORM, PHPUnit, Infection 0.33 (run via `docker compose exec php`).

**Spec:** `docs/superpowers/specs/2026-06-06-w4-mutation-test-chore-scheduling-design.md`

**Branch:** `fix/w4-mutation-test-chore-scheduling` (already created; spec already committed).

---

## Baseline facts (measured, do not re-derive)

A probe (Infection over `src/Entity`) established:
- Entity Covered-Code MSI is already **95%**; the dates in `Chore` recurrence are **already pinned** by `ChoreTest`'s data providers.
- **Killable gaps** (real): `Chore.php:187` `setTime(0,0)` (final midnight normalization — tests only assert `Y-m-d`, so the time mutation is invisible) and `ShoppingListItem.php:88` `getDisplayName` coalesce.
- **Dead code** (real bug): `Product::removeBarcode` inner block is a no-op → 3 unkillable mutants.
- **Residual equivalent mutants / framework noise on `Chore`** (expected, accepted, NOT test gaps): `:138` PublicVisibility on the `#[ORM\PreUpdate]` hook, `:179` redundant intermediate `setTime` (overwritten by `:187`), `:192` `(int)` cast (PHP coerces the numeric string anyway), `:204` `<` (the guard at `:211` absorbs the `<`→`<=` difference at equality). These cap achievable MSI below 100% and are handled by the honest floor, not by contorting code.
- Pure data-holder entities (`Barcode`, `StockEntry`, `RecipeIngredient`, `Recipe`, `Category`, `Location`, `User`) produce only `PublicVisibility`/getter noise → excluded.
- `Task` and `ShoppingListItem` transition methods are already fully killed → no work there.
- CI does **not** run Infection (`make mutate` is local-only). No CI gate is added.

## File structure

- **Modify** `backend/infection.json5` — add `src/Entity` to `source.directories`, add `source.excludes` for the 7 data-holder entities, update the stale comment, add `minMsi`/`minCoveredMsi`.
- **Modify** `backend/tests/Unit/Entity/ChoreTest.php` — add the midnight-normalization test.
- **Modify** `backend/tests/Unit/Entity/ShoppingListItemTest.php` — add the `getDisplayName` test.
- **Modify** `backend/src/Entity/Product.php` — simplify `removeBarcode` (remove dead block).
- **Create** `backend/tests/Unit/Entity/ProductTest.php` — unit test pinning `addBarcode`/`removeBarcode`.

All commands run from `/home/pavel/projects/personal/hestia/backend` unless noted. Commit with `git commit -s` (signoff) per repo convention; the `ratchet27` gh account is already active.

---

## Task 1: Widen Infection scope to logic entities

**Files:**
- Modify: `backend/infection.json5`

- [ ] **Step 1: Replace the `source` block and add the comment update**

Open `backend/infection.json5`. Replace the existing `source` block:

```json5
    "source": {
        // Start with services - most business logic lives here
        "directories": [
            "src/Service"
        ]
    },
```

with (covers services + logic-bearing entities; pure data holders excluded centrally):

```json5
    "source": {
        // Services hold most business logic; entities hold the rich pure logic
        // (Chore recurrence, ShoppingListItem transitions, Product barcode guard).
        // Pure data-holder entities (only accessors + ORM lifecycle) are excluded
        // so the MSI reflects real logic, not getter noise.
        "directories": [
            "src/Service",
            "src/Entity"
        ],
        "excludes": [
            "Barcode.php",
            "StockEntry.php",
            "RecipeIngredient.php",
            "Recipe.php",
            "Category.php",
            "Location.php",
            "User.php"
        ]
    },
```

- [ ] **Step 2: Verify the config parses and the excludes actually take effect**

Run a scoped mutation pass over the entities and inspect which files get mutated:

```bash
docker compose exec php vendor/bin/infection --threads=max --filter=src/Entity --no-progress 2>&1 | tail -15
```

Expected: it runs without a config/schema error, and the run covers `Chore`, `Task`, `Product`, `ShoppingListItem` only. Confirm the excluded entities are NOT mutated:

```bash
docker compose exec php sh -c 'grep -oE "src/Entity/[A-Za-z]+\.php" var/infection.log | sort -u'
```

Expected output contains `Chore.php`, `Product.php`, `ShoppingListItem.php`, `Task.php` and does **NOT** contain `Barcode.php`, `StockEntry.php`, `RecipeIngredient.php`, `Recipe.php`, `Category.php`, `Location.php`, `User.php`.

If an excluded file still appears, the exclude path form is wrong for this Infection version — change the entries to project-relative paths (e.g. `"src/Entity/Barcode.php"`) and re-run this step until the excluded set is gone.

- [ ] **Step 3: Commit**

```bash
git add infection.json5
git commit -s -m "test(infection): include logic entities, exclude pure data holders (#57)"
```

---

## Task 2: Pin the midnight-normalization contract on Chore::nextDueAt

This kills the `Chore.php:187` `setTime(0,0)` mutants. `markDone`/`reschedule`/`initializeNextDueAt` must always yield a `nextDueAt` at 00:00:00 regardless of the done-time's wall-clock — the existing tests only assert `Y-m-d`, so the normalization is currently unverified.

**Files:**
- Modify: `backend/tests/Unit/Entity/ChoreTest.php`

- [ ] **Step 1: Add the test**

In `backend/tests/Unit/Entity/ChoreTest.php`, insert this method immediately after `testMarkDoneWithFixedMonthlySchedule` (i.e. after the closing `}` of that method, before the `fixedMonthlyScheduleProvider` provider) — or anywhere inside the class:

```php
    public function testMarkDoneNormalizesNextDueToMidnightForEverySchedule(): void
    {
        // The done-instant carries a wall-clock time; nextDueAt must always be 00:00:00.
        $doneAt = new \DateTimeImmutable('2026-02-05 14:37:11');

        foreach ([ScheduleType::INTERVAL, ScheduleType::FIXED_WEEKLY, ScheduleType::FIXED_MONTHLY] as $type) {
            $chore = $this->createChore($type, 3);
            $chore->markDone($doneAt);

            static::assertSame(
                '00:00:00',
                $chore->getNextDueAt()->format('H:i:s'),
                sprintf('nextDueAt must be midnight for %s', $type->value),
            );
        }
    }
```

- [ ] **Step 2: Run the test — it passes on current (correct) code**

```bash
docker compose exec php bin/phpunit --filter testMarkDoneNormalizesNextDueToMidnightForEverySchedule
```

Expected: PASS (1 test, 3 assertions). This is a mutation-pinning test, not a red→green TDD test — the production code is already correct; the test exists to make the `setTime(0,0)` mutation detectable.

- [ ] **Step 3: Confirm the mutant is now killed**

```bash
docker compose exec php vendor/bin/infection --threads=max --filter=src/Entity/Chore.php --no-progress 2>&1 | tail -8
docker compose exec php sh -c 'grep "Chore.php:187" var/infection.log || echo "187 KILLED (no longer escaped)"'
```

Expected: `Chore.php:187` no longer appears under "Escaped mutants" (prints `187 KILLED ...`). Remaining escaped `Chore` lines (`138`, `179`, `192`, `204`) are the documented equivalent/noise mutants — leave them.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/Entity/ChoreTest.php
git commit -s -m "test(chore): pin nextDueAt midnight normalization (#57)"
```

---

## Task 3: Pin ShoppingListItem::getDisplayName fallback chain

Kills the `ShoppingListItem.php:88` `Coalesce` mutant on `return $this->product?->getName() ?? $this->customName ?? '';`.

**Files:**
- Modify: `backend/tests/Unit/Entity/ShoppingListItemTest.php`

- [ ] **Step 1: Add the test**

In `backend/tests/Unit/Entity/ShoppingListItemTest.php`, add the `Product` import alongside the existing `use` statements at the top:

```php
use App\Entity\Product;
```

Then add this method inside the class (e.g. after `testReviseAmountFlipsAutoToManualWhenAmountChanges`):

```php
    public function testGetDisplayNamePrefersProductThenCustomNameThenEmpty(): void
    {
        $product = new Product();
        $product->setName('Milk');

        // Product present → product name wins.
        $withProduct = new ShoppingListItem()->setProduct($product)->setCustomName('ignored');
        static::assertSame('Milk', $withProduct->getDisplayName());

        // No product, custom name set → custom name.
        $withCustom = new ShoppingListItem()->setCustomName('Eggs');
        static::assertSame('Eggs', $withCustom->getDisplayName());

        // Neither → empty string.
        $empty = new ShoppingListItem();
        static::assertSame('', $empty->getDisplayName());
    }
```

- [ ] **Step 2: Run the test**

```bash
docker compose exec php bin/phpunit --filter testGetDisplayNamePrefersProductThenCustomNameThenEmpty
```

Expected: PASS (1 test, 3 assertions).

- [ ] **Step 3: Confirm the mutant is killed**

```bash
docker compose exec php vendor/bin/infection --threads=max --filter=src/Entity/ShoppingListItem.php --no-progress 2>&1 | tail -8
docker compose exec php sh -c 'grep "ShoppingListItem.php:88" var/infection.log || echo "88 KILLED (no longer escaped)"'
```

Expected: line `:88` no longer escaped.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/Entity/ShoppingListItemTest.php
git commit -s -m "test(shopping-list): pin getDisplayName fallback chain (#57)"
```

---

## Task 4: Fix Product::removeBarcode dead code

The inner block re-sets the *same* product on a barcode being removed — a no-op (which is why its mutants are unkillable). `Barcode::product` is non-nullable (`#[ORM\JoinColumn(nullable: false)]`) and the association uses `orphanRemoval: true` + `cascade: ['persist','remove']`, so `removeElement` already detaches/deletes the barcode. Remove the dead block.

**Files:**
- Create: `backend/tests/Unit/Entity/ProductTest.php`
- Modify: `backend/src/Entity/Product.php`

- [ ] **Step 1: Write a unit test pinning add/remove behavior**

Create `backend/tests/Unit/Entity/ProductTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Entity;

use App\Entity\Barcode;
use App\Entity\Product;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testAddBarcodeLinksBothSides(): void
    {
        $product = new Product();
        $barcode = new Barcode();

        $product->addBarcode($barcode);

        static::assertTrue($product->getBarcodes()->contains($barcode));
        static::assertSame($product, $barcode->getProduct());
    }

    public function testAddBarcodeIsIdempotent(): void
    {
        $product = new Product();
        $barcode = new Barcode();

        $product->addBarcode($barcode);
        $product->addBarcode($barcode);

        static::assertCount(1, $product->getBarcodes());
    }

    public function testRemoveBarcodeDetachesFromCollection(): void
    {
        $product = new Product();
        $barcode = new Barcode();
        $product->addBarcode($barcode);

        $product->removeBarcode($barcode);

        static::assertFalse($product->getBarcodes()->contains($barcode));
        static::assertCount(0, $product->getBarcodes());
    }
}
```

- [ ] **Step 2: Run the tests against current code — they pass**

```bash
docker compose exec php bin/phpunit tests/Unit/Entity/ProductTest.php
```

Expected: PASS (3 tests). They pass now because the dead block is a no-op; they pin the contract so the simplification in Step 3 is safe.

- [ ] **Step 3: Remove the dead block**

In `backend/src/Entity/Product.php`, replace:

```php
    public function removeBarcode(Barcode $barcode): static
    {
        if ($this->barcodes->removeElement($barcode)) {
            if ($barcode->getProduct() === $this) {
                $barcode->setProduct($this);
            }
        }

        return $this;
    }
```

with:

```php
    public function removeBarcode(Barcode $barcode): static
    {
        // orphanRemoval handles detach/delete; Barcode::product is non-nullable,
        // so there is no inverse side to clear here.
        $this->barcodes->removeElement($barcode);

        return $this;
    }
```

- [ ] **Step 4: Re-run the Product tests — still green**

```bash
docker compose exec php bin/phpunit tests/Unit/Entity/ProductTest.php
```

Expected: PASS (3 tests).

- [ ] **Step 5: Confirm the dead-code mutants are gone**

```bash
docker compose exec php vendor/bin/infection --threads=max --filter=src/Entity/Product.php --no-progress 2>&1 | tail -8
docker compose exec php sh -c 'grep -E "Product.php:(217|218|219)" var/infection.log || echo "removeBarcode mutants gone"'
```

Expected: lines 217–219 no longer present (the code that generated them is deleted).

- [ ] **Step 6: Commit**

```bash
git add src/Entity/Product.php tests/Unit/Entity/ProductTest.php
git commit -s -m "fix(product): drop no-op inverse-side write in removeBarcode (#57)"
```

---

## Task 5: Set the honest minCoveredMsi floor

**Files:**
- Modify: `backend/infection.json5`

- [ ] **Step 1: Measure the achieved MSI over the full configured scope**

```bash
make mutate
```

(`make mutate` = `docker compose exec php vendor/bin/infection --show-mutations`.) Read the final metrics block, e.g.:

```
Metrics:
         Mutation Code Coverage: 100%
         Covered Code MSI: 9X%
```

Record the printed **Covered Code MSI** integer (call it `M`) and the overall **MSI** integer (call it `N`).

- [ ] **Step 2: Add the floors just below the measured values**

In `backend/infection.json5`, add two top-level keys (sibling of `"source"`, `"mutators"`, etc.). Set each to the measured value rounded **down** to the nearest whole percent (so the residual equivalent mutants on `Chore` don't make the gate unachievable, while still catching regressions):

```json5
    // Local regression guard (CI does not run Infection). Floor reflects the
    // achieved value; residual Chore mutants (:138 ORM hook, :179/:192/:204
    // equivalent) are accepted, not masked by lowering this further.
    "minMsi": <N>,
    "minCoveredMsi": <M>,
```

Replace `<N>` and `<M>` with the integers recorded in Step 1 (e.g. if Covered Code MSI is 92% and MSI is 88%, use `"minMsi": 88` and `"minCoveredMsi": 92`).

- [ ] **Step 3: Re-run and confirm the gate passes**

```bash
make mutate
```

Expected: exits 0 (no "below the minimum" error). If it reports being below the threshold by a hair (nondeterministic timeouts can shave a point), lower the two values by 1 and re-run until green — the floor must sit at or just under the stable achieved value.

- [ ] **Step 4: Commit**

```bash
git add infection.json5
git commit -s -m "test(infection): add local minCoveredMsi regression floor (#57)"
```

---

## Task 6: Final gates and PR

- [ ] **Step 1: Run the backend lint gate (auto-fixes)**

```bash
make lint
```

Expected: green. `make lint` runs `rector → mago format → mago lint → mago analyze → phpstan`. If it rewrites files, stage only the files this work touched (never `git add -A`).

- [ ] **Step 2: Run the full test suite**

```bash
make test
```

Expected: green.

- [ ] **Step 3: Commit any lint auto-fixes (if any)**

```bash
git add tests/Unit/Entity/ChoreTest.php tests/Unit/Entity/ShoppingListItemTest.php tests/Unit/Entity/ProductTest.php src/Entity/Product.php infection.json5
git commit -s -m "style: apply make lint fixes (#57)" || echo "nothing to commit"
```

- [ ] **Step 4: Push and open the PR**

```bash
git push -u origin fix/w4-mutation-test-chore-scheduling
gh pr create --fill --title "test(infection): mutation-test Chore scheduling + fix removeBarcode dead code (#57)"
```

PR description must note:
- Infection now covers logic entities (`Chore`, `Task`, `Product`, `ShoppingListItem`); pure data holders excluded in config.
- Killed real gaps: `Chore::nextDueAt` midnight normalization, `ShoppingListItem::getDisplayName` fallback.
- Fixed the `Product::removeBarcode` no-op inverse-side write (latent dead code).
- Added a local `minCoveredMsi` floor; **CI does not run Infection** — the metric stays advisory/local, no CI gate added.
- Residual `Chore` survivors (`:138` ORM `PreUpdate` hook PublicVisibility, `:179`/`:192`/`:204`) are documented **equivalent mutants** accepted by the floor — not test gaps.
- Closes #57.

---

## Self-review notes

- **Spec coverage:** infection scope+excludes (Task 1) ✓; Chore math pinned via midnight contract — the dates were already pinned, the real gap was normalization (Task 2) ✓; getDisplayName (Task 3) ✓; removeBarcode fix (Task 4) ✓; minCoveredMsi floor (Task 5) ✓; lint+test+no-CI-gate (Task 6) ✓. The spec's `Task::setDone` mention is intentionally dropped — the probe proved Task is already fully killed.
- **Equivalent mutants:** The spec assumed the clamp branches were unpinned; the probe disproved that. The plan reflects measured reality (kill 187/88/removeBarcode; accept 138/179/192/204) rather than writing tests that cannot kill equivalent mutants.
- **Type/name consistency:** `createChore(ScheduleType, int)`, `getNextDueAt()`, `getDisplayName()`, `getBarcodes()`, `addBarcode()`, `removeBarcode()`, `getProduct()` all match the entity signatures verified in the source.
- **Exclude path format** is verified empirically in Task 1 Step 2 with an explicit adjust-and-retry instruction (the one config detail that varies by Infection version).
