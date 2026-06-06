# Shopping-Item AUTO→MANUAL Flip Rule Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the "user action flips an item AUTO→MANUAL so auto-reconciliation can't overwrite it" invariant out of three scattered spots in `ShoppingListService` onto `ShoppingListItem` as intent-named methods, with behavior preserved exactly.

**Architecture:** Add `isAuto()`, `claimManual()`, and `reviseAmount(int)` to the `ShoppingListItem` entity carrying the transition rules. Route `ShoppingListService`'s user-edit paths (`addItem` merge, `updateItem`) through these methods, and switch `upsertAutoItem`'s guard to `isAuto()`. Pure unit tests pin the entity transitions; the existing functional tests prove API behavior is unchanged.

**Tech Stack:** PHP 8.x, Symfony, Doctrine ORM, PHPUnit, Foundry. Backend commands run via `docker compose exec php`; `make lint` and `make test` run from `backend/`. Linting/testing per `backend/AGENTS.md`.

---

## File Structure

- **Modify:** `backend/src/Entity/ShoppingListItem.php` — add `isAuto()`, `claimManual()`, `reviseAmount(int)`; move the two flip-rule comments here as docblocks.
- **Modify:** `backend/src/Service/ShoppingListService.php` — route `addItem`/`updateItem` through the new methods; `upsertAutoItem` guard → `isAuto()`; delete stale comments.
- **Create:** `backend/tests/Unit/Entity/ShoppingListItemTest.php` — pure unit tests for the transitions.
- **Unchanged (regression proof):** `backend/tests/Functional/Controller/Api/Internal/V1/{ShoppingListControllerTest,ShoppingListAutoAddTest,RecipeControllerTest}.php`.

---

## Task 1: Add transition methods to `ShoppingListItem` (TDD)

**Files:**
- Create: `backend/tests/Unit/Entity/ShoppingListItemTest.php`
- Modify: `backend/src/Entity/ShoppingListItem.php`

- [ ] **Step 1: Write the failing unit test**

Create `backend/tests/Unit/Entity/ShoppingListItemTest.php`. Mirror the structure of the existing `backend/tests/Unit/Entity/ChoreTest.php` (namespace `App\Tests\Unit\Entity`, extends `PHPUnit\Framework\TestCase`).

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Entity;

use App\Entity\ShoppingListItem;
use App\Enum\ShoppingListSource;
use PHPUnit\Framework\TestCase;

final class ShoppingListItemTest extends TestCase
{
    public function testReviseAmountFlipsAutoToManualWhenAmountChanges(): void
    {
        $item = (new ShoppingListItem())->setAmount(2)->setSource(ShoppingListSource::AUTO);

        $item->reviseAmount(5);

        self::assertSame(5, $item->getAmount());
        self::assertSame(ShoppingListSource::MANUAL, $item->getSource());
    }

    public function testReviseAmountKeepsAutoWhenAmountUnchanged(): void
    {
        $item = (new ShoppingListItem())->setAmount(3)->setSource(ShoppingListSource::AUTO);

        $item->reviseAmount(3);

        self::assertSame(3, $item->getAmount());
        self::assertSame(ShoppingListSource::AUTO, $item->getSource());
    }

    public function testReviseAmountKeepsRecipeOnChange(): void
    {
        $item = (new ShoppingListItem())->setAmount(2)->setSource(ShoppingListSource::RECIPE);

        $item->reviseAmount(7);

        self::assertSame(7, $item->getAmount());
        self::assertSame(ShoppingListSource::RECIPE, $item->getSource());
    }

    public function testReviseAmountKeepsManualOnChange(): void
    {
        $item = (new ShoppingListItem())->setAmount(2)->setSource(ShoppingListSource::MANUAL);

        $item->reviseAmount(9);

        self::assertSame(9, $item->getAmount());
        self::assertSame(ShoppingListSource::MANUAL, $item->getSource());
    }

    public function testClaimManualFromAuto(): void
    {
        $item = (new ShoppingListItem())->setSource(ShoppingListSource::AUTO);

        $item->claimManual();

        self::assertSame(ShoppingListSource::MANUAL, $item->getSource());
    }

    public function testClaimManualFromRecipe(): void
    {
        $item = (new ShoppingListItem())->setSource(ShoppingListSource::RECIPE);

        $item->claimManual();

        self::assertSame(ShoppingListSource::MANUAL, $item->getSource());
    }

    public function testIsAutoReflectsSource(): void
    {
        $item = new ShoppingListItem();

        $item->setSource(ShoppingListSource::AUTO);
        self::assertTrue($item->isAuto());

        $item->setSource(ShoppingListSource::MANUAL);
        self::assertFalse($item->isAuto());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php vendor/bin/phpunit tests/Unit/Entity/ShoppingListItemTest.php`
Expected: FAIL — `Error: Call to undefined method App\Entity\ShoppingListItem::reviseAmount()` (or `isAuto`/`claimManual`).

- [ ] **Step 3: Add the methods to the entity**

In `backend/src/Entity/ShoppingListItem.php`, add the following methods (place them next to the existing `getSource`/`setSource`, around line 125). Do **not** remove `setSource`/`setAmount` — Doctrine hydration, Foundry factories, and ObjectMapper rely on them.

```php
    public function isAuto(): bool
    {
        return $this->source === ShoppingListSource::AUTO;
    }

    /**
     * User explicitly added/owns this item: force it MANUAL so
     * auto-reconciliation can't overwrite it.
     */
    public function claimManual(): static
    {
        $this->source = ShoppingListSource::MANUAL;

        return $this;
    }

    /**
     * Revise the amount. A real change to an AUTO item is a user assertion of
     * ownership that flips it to MANUAL (RECIPE/MANUAL keep their source).
     */
    public function reviseAmount(int $amount): static
    {
        if ($amount !== $this->amount && $this->source === ShoppingListSource::AUTO) {
            $this->source = ShoppingListSource::MANUAL;
        }

        $this->amount = $amount;

        return $this;
    }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php vendor/bin/phpunit tests/Unit/Entity/ShoppingListItemTest.php`
Expected: PASS — 7 tests, all green.

- [ ] **Step 5: Lint the backend**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: green (rector → mago format → mago lint → mago analyze → phpstan). `make lint` may auto-fix files — re-check `git status` and stage only the files this task touches.

- [ ] **Step 6: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Entity/ShoppingListItem.php backend/tests/Unit/Entity/ShoppingListItemTest.php
git commit -s -m "feat(shopping-list): add AUTO→MANUAL transition methods to ShoppingListItem (W3, #58)"
```

---

## Task 2: Route the service's user-edit paths through the entity methods

**Files:**
- Modify: `backend/src/Service/ShoppingListService.php`

This task is behavior-preserving. The existing functional tests are the regression proof — they must stay green with no edits.

- [ ] **Step 1: Confirm the regression tests pass before the change**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php vendor/bin/phpunit \
  tests/Functional/Controller/Api/Internal/V1/ShoppingListControllerTest.php \
  tests/Functional/Controller/Api/Internal/V1/ShoppingListAutoAddTest.php \
  tests/Functional/Controller/Api/Internal/V1/RecipeControllerTest.php
```
Expected: PASS (baseline — establishes green before refactor).

- [ ] **Step 2: Update `upsertAutoItem`'s guard**

In `backend/src/Service/ShoppingListService.php`, the guard currently reads:

```php
            // If it's a manual item, don't touch it
            if ($existing->getSource() !== ShoppingListSource::AUTO) {
                return;
            }
```

Replace it with:

```php
            // If it's not an auto item (manual/recipe), don't touch it
            if (!$existing->isAuto()) {
                return;
            }
```

- [ ] **Step 3: Update the `addItem` merge branch**

Currently:

```php
            if ($existing !== null) {
                // Merge: use max amount, convert to manual
                $existing->setAmount(max($existing->getAmount(), $request->amount));
                $existing->setSource(ShoppingListSource::MANUAL);
                if ($request->note !== null) {
                    $existing->setNote($request->note);
                }

                $this->entityManager->flush();

                return $existing;
            }
```

Replace the two `set*` lines (the merge + convert-to-manual) with the entity methods. The `claimManual()` is unconditional, matching today's always-manual-on-add semantics:

```php
            if ($existing !== null) {
                $existing->reviseAmount(max($existing->getAmount(), $request->amount))->claimManual();
                if ($request->note !== null) {
                    $existing->setNote($request->note);
                }

                $this->entityManager->flush();

                return $existing;
            }
```

- [ ] **Step 4: Update `updateItem`'s flip-and-set block**

Currently:

```php
        // If user manually changes amount on an AUTO item, convert to MANUAL
        if (
            $request->amount !== null
            && $item->getSource() === ShoppingListSource::AUTO
            && $request->amount !== $item->getAmount()
        ) {
            $item->setSource(ShoppingListSource::MANUAL);
        }

        if ($request->amount !== null) {
            $item->setAmount($request->amount);
        }
```

Replace both blocks with a single call — `reviseAmount` carries the flip rule:

```php
        if ($request->amount !== null) {
            $item->reviseAmount($request->amount);
        }
```

Leave the `note` and `done` handling below unchanged.

- [ ] **Step 5: Verify the user-edit paths no longer set MANUAL directly**

Run: `cd /home/pavel/projects/personal/hestia/backend && grep -n "setSource(ShoppingListSource::MANUAL)" src/Service/ShoppingListService.php`
Expected: **no output** (the only remaining `setSource` is the AUTO one in `upsertAutoItem`'s create branch). Confirm with `grep -n "setSource" src/Service/ShoppingListService.php` → only the `ShoppingListSource::AUTO` line at the new-item creation remains.

- [ ] **Step 6: Run the full backend test suite**

Run: `cd /home/pavel/projects/personal/hestia/backend && make test`
Expected: PASS — all functional + unit tests green, no edits to the functional tests required.

- [ ] **Step 7: Lint the backend**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: green. If `make lint` auto-fixes, re-check `git status` and stage only this task's file.

- [ ] **Step 8: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Service/ShoppingListService.php
git commit -s -m "refactor(shopping-list): route user-edit paths through entity transition methods (W3, #58)"
```

---

## Task 3: Final verification

**Files:** none (verification only)

- [ ] **Step 1: Full gate — lint + test**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint && make test`
Expected: both green.

- [ ] **Step 2: Confirm acceptance criteria**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend
grep -n "setSource(ShoppingListSource::MANUAL)" src/Service/ShoppingListService.php   # expect: empty
grep -n "reviseAmount\|claimManual\|isAuto" src/Service/ShoppingListService.php       # expect: the call sites
```
Expected: no MANUAL `setSource` in the service; `reviseAmount`/`claimManual`/`isAuto` appear at the call sites.

- [ ] **Step 3: Push and open the PR** (only if the user asks to open a PR)

```bash
cd /home/pavel/projects/personal/hestia
gh auth switch -u ratchet27
git push -u origin w3-shopping-item-flip-rule
gh pr create --fill --body "Closes #58"
```

---

## Notes for the implementer

- **Docker for PHP:** never run `php`/`composer`/`phpunit` directly on the host — always `docker compose exec php …` from `backend/`. `make test`/`make lint` wrap this.
- **`make lint` auto-fixes:** it rewrites files in place. After running it, stage explicitly (`git add <file>`); never `git add -A`. `config/reference.php` is generated and gitignored — ignore its churn.
- **Behavior preservation is the contract:** if any functional test in `ShoppingListControllerTest`/`ShoppingListAutoAddTest`/`RecipeControllerTest` fails, the refactor changed behavior — stop and reconcile, don't edit the test to match.
