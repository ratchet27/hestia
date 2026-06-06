# W3 — Move the AUTO→MANUAL flip rule onto `ShoppingListItem`

**Date:** 2026-06-06
**Issue:** #58 (architecture-review, tech-debt) · Review ref: `docs/reviews/2026-06-05-backend-architecture/README.md` § W3
**Area:** backend / shopping-list · **Severity:** Medium · **Effort:** S

## Problem

The invariant *"a user action that asserts ownership flips an item from AUTO to
MANUAL, so auto-reconciliation never overwrites it"* is spread across three
spots in `ShoppingListService`, with **two distinct flip rules** that can drift:

- `upsertAutoItem` (`:66`) — skips non-AUTO items.
- `addItem` merge branch (`:121–122`) — unconditionally sets `MANUAL`.
- `updateItem` (`:159–166`) — sets `MANUAL` only when the amount actually changes.

`ShoppingListItem` is an anemic data bag, so the invariant lives nowhere it can
be enforced in one place. A future caller that sets `amount` without flipping
`source` would let the next stock event silently revert the user's change.

## Decisions (from brainstorming)

1. **RECIPE handling — preserve exactly.** Today only AUTO flips on amount
   change; a RECIPE item edited by the user stays `RECIPE`. This is safe because
   auto-reconciliation (`upsertAutoItem`/`removeAutoItem`) only ever touches
   AUTO items, so RECIPE items are never overwritten regardless. This PR keeps
   that behavior unchanged — no broader "any non-MANUAL flips" rule.
2. **Entity API — `claimManual()` + `reviseAmount(int)` + `isAuto()`**, per the
   issue verbatim. Intent-named, two distinct call-site meanings (claim vs
   revise), plus an `isAuto()` readability helper for the upsert guard.

## Design

### 1. Entity methods — `src/Entity/ShoppingListItem.php`

```php
public function isAuto(): bool
{
    return $this->source === ShoppingListSource::AUTO;
}

/** User explicitly added/owns this item: force it MANUAL so auto-reconciliation can't overwrite it. */
public function claimManual(): static
{
    $this->source = ShoppingListSource::MANUAL;

    return $this;
}

/** Revise the amount; a real change to an AUTO item is a user assertion that flips it to MANUAL. */
public function reviseAmount(int $amount): static
{
    if ($amount !== $this->amount && $this->source === ShoppingListSource::AUTO) {
        $this->source = ShoppingListSource::MANUAL;
    }
    $this->amount = $amount;

    return $this;
}
```

These methods set `$this->source`/`$this->amount` directly — they are the
authoritative mutators carrying the rule. `setSource`/`setAmount` stay public
(Doctrine hydration, Foundry factories, ObjectMapper rely on them); per the
issue's honest note the win is **centralization + intent at call sites**, not
unbypassable sealing. Fully sealing the setters is a possible later step, out of
scope here.

### 2. Service refactor — `src/Service/ShoppingListService.php`

- **`upsertAutoItem`** (`:66`): `$existing->getSource() !== ShoppingListSource::AUTO`
  → `!$existing->isAuto()`. AUTO-only path otherwise unchanged.
- **`addItem` merge branch** (`:120–125`): replace
  `setAmount(max(...))` + `setSource(MANUAL)` with
  `$existing->reviseAmount(max($existing->getAmount(), $request->amount))->claimManual();`.
  `claimManual()` is unconditional, matching today's always-manual-on-add.
  Note handling (`if ($request->note !== null) ...`) unchanged.
- **`updateItem`** (`:159–170`): replace the manual-flip `if` block **and** the
  `setAmount` call with a single
  `if ($request->amount !== null) { $item->reviseAmount($request->amount); }`.
  Note/done handling unchanged (they don't flip source today).

Move the explanatory comments (`:120` "Merge: use max amount, convert to
manual", `:159` "If user manually changes amount on an AUTO item, convert to
MANUAL") onto the entity methods' docblocks; delete the stale service-side ones.

### 3. Tests

**New `tests/Unit/Entity/ShoppingListItemTest.php`** (pure, no DB — also feeds
W4 / #57's goal of mutation-testing `src/Entity`):

- `reviseAmount` on AUTO with a *different* amount → MANUAL + amount set
- `reviseAmount` on AUTO with the *same* amount → stays AUTO (and amount set)
- `reviseAmount` on RECIPE with a different amount → **stays RECIPE** (preserved quirk)
- `reviseAmount` on MANUAL with a different amount → stays MANUAL
- `claimManual` from AUTO → MANUAL
- `claimManual` from RECIPE → MANUAL

**Existing functional tests** stay unchanged and green as the
behavior-preservation proof: `ShoppingListControllerTest`,
`ShoppingListAutoAddTest`, `RecipeControllerTest`.

## Scope guardrails (NOT doing)

- No `ShoppingList` aggregate; no moving add/remove/merge orchestration onto the entity.
- No sealing / removing the Doctrine setters.
- No change to the `AUTO/MANUAL/RECIPE` enum or any API response/behavior.
- No change to RECIPE flip behavior.

## Acceptance criteria

- The AUTO→MANUAL transition logic lives on `ShoppingListItem`
  (`claimManual` + `reviseAmount`), not replicated in the service.
- `addItem`/`updateItem` call the entity methods; behavior unchanged (both flip
  rules preserved exactly, incl. RECIPE-stays-RECIPE).
- Auto-reconciliation still never touches MANUAL (or RECIPE) items.
- Unit tests cover both transitions and the no-touch cases.
- `make lint` + `make test` green.

## Verification

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint && make test
# expect: no MANUAL setSource left in user-edit paths
grep -n "setSource(ShoppingListSource::MANUAL)" src/Service/ShoppingListService.php
```
