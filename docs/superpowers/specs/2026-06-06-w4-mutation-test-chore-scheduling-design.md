# W4 — Mutation-test the Chore scheduling math (#57)

**Issue:** #57 (architecture-review, tech-debt) · **Review ref:** `docs/reviews/2026-06-05-backend-architecture/README.md` § W4
**Severity:** Medium · **Effort:** S–M · **Area:** backend / testing (Infection)

## Problem

Infection is scoped to `src/Service` only. The most intricate *pure* logic in the app —
the `Chore` recurrence math, including weekly wrap-around and month-length clamping — lives in
`src/Entity` and is never mutation-tested. Tests can pass without actually exercising those branches.

## Grounding (measured, not assumed)

A baseline probe (`infection` with `source.directories: ["src/Entity"]`) was run before designing.
Results that shape this design:

- **Entity Covered-Code MSI is already 95%** (100% mutation code coverage). The suite is strong;
  this is a *targeted* gap, not a rewrite.
- **Meaningful survivors are concentrated in `Chore`**, exactly where the review predicted:
  - `Chore.php:179` `IncrementInteger` ×2 — the weekly wrap `$daysUntil += 7`.
  - `Chore.php:187` `IncrementInteger` ×2 / `:192` `CastInt` / `:204` `LessThan` — the
    `nextMonthDay` clamp + "month too short, advance again" branch.
  - `Chore.php:138` `PublicVisibility` — the `#[ORM\PreUpdate]` lifecycle hook (framework noise).
- **`Task` is already fully pinned** — zero survivors. The issue's assumption that `Task::setDone`
  needs new tests is **wrong**; no Task work is needed.
- **`ShoppingListItem` W3 transitions are already fully pinned.** Only one minor survivor:
  `ShoppingListItem.php:88` `Coalesce` on `getDisplayName` (`?->getName() ?? customName ?? ''`).
- **`Product::removeBarcode` has 3 unkillable survivors** (`:217` `IfNegation`, `:218` `Identical`,
  `:219` `MethodCallRemoval`) because the inner block is a **no-op / dead code** (see §3).
- Everything else (`Product` getters, `User` roles/accessors, `Category`/`Location` getters,
  `Barcode`/`StockEntry`/`RecipeIngredient`/`Recipe`) is pure accessor / framework `PublicVisibility`
  noise.
- **CI does not run Infection** (`make mutate` is local-only; no `infection`/`mutation` step in
  `.github/workflows/`). The metric is advisory/local — **no CI gate is added**.

## Scope

**In scope**

1. Widen Infection to logic-bearing entities and de-noise pure data holders.
2. Strengthen `ChoreTest` so the weekly-wrap and monthly-clamp mutants are killed.
3. Kill the lone `getDisplayName` coalesce survivor (ShoppingListItem stays measured).
4. Fix the `Product::removeBarcode` dead code.
5. Add a local `minCoveredMsi` floor.

**Out of scope / do NOT**

- Change the discrete-entry stock model, response mapping, or any API/JSON shape.
- Chase 100% MSI or test trivial getters.
- Add Infection to CI or gate CI on MSI.
- Build a broader error/exception taxonomy.

## Design

### 1. Infection config (`infection.json5`)

- Add `"src/Entity"` to `source.directories`.
- Add `source.excludes` listing the **pure data-holder entities** — entities with no conditional /
  domain logic, only property accessors + ORM lifecycle:
  `Barcode`, `StockEntry`, `RecipeIngredient`, `Recipe`, `Category`, `Location`, `User`.
  (Paths relative to the configured source dirs, e.g. `Entity/Barcode.php`.) Centralizing the
  de-noising in config keeps the entity files clean and puts the exclusion list in one readable place.
- **Kept measured** (real branching / transition logic): `Chore`, `Task`, `Product`,
  `ShoppingListItem`.
- Update the stale `// Start with services - most business logic lives here` comment to describe the
  new scope (services + logic-bearing entities; pure data holders excluded).
- Add `minCoveredMsi` (and, if appropriate, `minMsi`) under a `metrics`/top-level key as supported by
  the Infection schema, set to an **honest floor** derived from the final measured run after the tests
  below — not lowered to mask noise. Exact value is fixed during implementation from the real run
  (target: at or just below the achieved measured MSI, expected ~95–100%).

### 2. Strengthen `ChoreTest` (core of W4)

Add boundary cases targeting the surviving mutants. Use `markDone`/`reschedule`/`initializeNextDueAt`
public entry points (the math is private) with fixed `DateTimeImmutable` anchors:

- **Weekly wrap** — a case where the target weekday **equals** the current weekday, so
  `daysUntil <= 0` and the schedule must advance a full `+7` days (not 0, not +6/+8). Kills the
  `IncrementInteger` on `+= 7` and pins the `<= 0` boundary.
- **Monthly clamp** — Jan 31 → Feb with target day 31 (clamp to 28, and 29 in a leap year); and the
  "month too short to reach target, advance one more month and clamp again" branch. These kill the
  `CastInt` on `(int) format('t')`, the `LessThan` comparison, and the `IncrementInteger` in
  `nextMonthDay`. Include both a leap-year (e.g. 2028) and non-leap (e.g. 2026) February case.

Follow the existing data-provider style already in `ChoreTest`.

### 3. Kill the `getDisplayName` coalesce survivor

Add a small `ShoppingListItemTest` (or extend an existing one) asserting `getDisplayName` across the
fallback chain: product name present → product name; no product but `customName` set → customName;
neither → `''`. This kills the `Coalesce` mutant since ShoppingListItem stays measured.

### 4. Fix `Product::removeBarcode` dead code

Current:

```php
public function removeBarcode(Barcode $barcode): static
{
    if ($this->barcodes->removeElement($barcode)) {
        if ($barcode->getProduct() === $this) {
            $barcode->setProduct($this); // no-op: re-sets the same product
        }
    }
    return $this;
}
```

`Barcode::setProduct(Product $product)` is **non-nullable** and the association uses orphanRemoval, so
a removed Barcode is deleted, not re-parented — the inner block does nothing (which is why its mutants
are unkillable). **Remove the dead inner `if` block**, leaving:

```php
public function removeBarcode(Barcode $barcode): static
{
    $this->barcodes->removeElement($barcode);
    return $this;
}
```

Verification before claiming the fix is safe:
- Confirm the `Product`↔`Barcode` mapping uses `orphanRemoval: true` (and/or cascade) so removal still
  deletes the Barcode rather than leaving an orphan with a dangling FK.
- Confirm existing functional barcode tests still pass; add/adjust a test if removal behavior isn't
  already covered.

## Acceptance criteria

- `infection.json5` includes `src/Entity` with pure data holders in `source.excludes`; the stale
  comment is updated.
- Running Infection **kills** the `Chore` weekly-wrap and monthly-clamp mutants, the
  `ShoppingListItem::getDisplayName` coalesce mutant, and the `Product::removeBarcode` mutants
  (now removed via the fix).
- `minCoveredMsi` is set to an honest floor reflecting the real measured value (noise handled by the
  excludes, not by lowering the bar).
- `make lint` and `make test` are green.
- No API response shape change; no CI Infection gate.

## Verification

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint && make test
# Mutation run over the new scope (services + logic entities):
docker compose exec php vendor/bin/infection --threads=max
grep -n "directories\|excludes\|minCoveredMsi" infection.json5
```

Confirm in the Infection report that the previously-escaped `Chore` (179/187/192/204),
`ShoppingListItem` (88), and `Product` (217–219) mutants are gone.

## Hard-evaluate notes (verify, don't trust)

- Re-confirm the existing `@infection-ignore-all` reasons still hold post-W1/W3
  (`ShoppingListService.php:44`, `StockEntryService.php:232,141`).
- The `infection.json5` "Start with services" comment is stale — update it.
- Do **not** assume CI runs Infection — it does not. Keep it advisory; say so in the PR.
- Treat the `Product::removeBarcode` change as behavior-relevant: verify orphanRemoval before deleting
  the block.
