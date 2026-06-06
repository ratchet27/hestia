# Recipes Spec Reconciliation — Design (issue #62 / D1)

**Date:** 2026-06-06
**Issue:** [#62](https://github.com/ratchet27/hestia/issues/62) — "D1 — Spec describes recipes as unbuilt, but the backend feature shipped"
**Review ref:** `docs/reviews/2026-06-05-backend-architecture/README.md` § D1
**Type:** Documentation reconciliation. **No code changes.**

## Problem

`docs/home_erp_specification.md` is explicitly the authoritative "as-built source
of truth for what exists," yet it describes the recipe feature as unbuilt while a
complete recipe feature has shipped (backend + integrated frontend). The doc
misleads anyone — human or agent — who trusts it.

## Verified ground truth (don't trust the doc — this was checked against code)

**Backend — fully shipped** (`backend/src/Service/RecipeService.php`,
`backend/src/Controller/Api/Internal/V1/RecipeController.php`,
`backend/src/Entity/{Recipe,RecipeIngredient}.php`):

- CRUD: `list` / `getResponse` / `create` / `update` / `delete` (+ matching
  controller routes `index/show/create/update/delete`).
- `cook(id)` — per-ingredient fulfillment check (in-stock count vs.
  `requiredCount`); throws `RecipeNotCookableException` with the missing-product
  list if any ingredient is short; otherwise consumes stock across locations for
  ingredients flagged `consumeOnCook`, then dispatches `StockChangedMessage`
  (after commit) to reconcile the shopping list.
- `addMissingToShoppingList(id)` — adds each ingredient's shortfall
  (`max(0, requiredCount - inStock)`) as a `ShoppingListItem` with
  `source = ShoppingListSource::RECIPE`; skips products already on the list.

**Frontend — fully integrated** (`frontend/src/features/recipes/RecipesPage.tsx`,
`frontend/src/api/queries/recipes.ts`): real query layer (`useRecipes`,
`useCreateRecipe`, `useUpdateRecipe`, `useDeleteRecipe`, `useCookRecipe`,
`useAddMissingToShoppingList`) over the generated API client. **Not mock data.**

**Shipped in** commit `c6db660` "feat(recipes): native stock-aware recipes (v2)".

**Genuinely still unbuilt (stays in roadmap):** meal-planning calendar — confirmed
absent from code.

## Stale claims to fix

| # | Location | Current (wrong) | Action |
|---|----------|-----------------|--------|
| 1 | spec §12 (Shopping List), lines ~232–233 | "`source = recipe` … **not wired** — recipes are a roadmap item (§18)" | Replace with: source is active, points to new Recipes section |
| 2 | spec §15 (UI Structure), Pages list | Recipes page not listed | Add Recipes to Pages |
| 3 | spec §15, lines ~284–285 | "Recipes page … **placeholder** running on mock data; not backend-integrated — §18" | Delete the note |
| 4 | spec §18 (Roadmap) v2, lines ~316–317 | "**Recipes**: fulfillment check, shopping-list generation, stock consumption …" | Remove (built) |
| 5 | `features_overview.md`, Postponed (v2+), line ~43 | "recipe fulfillment UI" | Remove; add recipes to built-features list |

## Design decisions (approved)

1. **Add a dedicated, numbered Recipes section** (peer of Stock / Shopping List /
   Tasks & Chores) rather than only patching inline notes — the spec is a
   feature-by-feature "as-built" doc and recipes now warrant their own entry.
2. **Placement:** insert as `## 13. Recipes`, immediately after §12 Shopping List
   (recipes feed the shopping list and consume stock — logically adjacent).
3. **Accept the renumber.** Cross-reference audit shows the **only** numbered
   section referenced elsewhere in the spec is **§18 (Roadmap)**, referenced 10
   times; no text references §12–§17 by number. So the insert is mechanical and
   fully greppable.

## Concrete edits

### Edit A — Insert `## 13. Recipes` after §12

```markdown
## 13. Recipes

Recipes are ingredient sets (product + required count, each with a
`consume on cook` flag) for checking what you can make and restocking for what
you can't.

Features:
- CRUD (name, ingredients)
- **fulfillment check** — required count vs. current stock, per ingredient
- **cook** — allowed only when every ingredient is in stock; consumes stock
  (across locations) for ingredients flagged `consume on cook`, then reconciles
  the shopping list. Blocks with the missing-product list if any ingredient is
  short.
- **add missing to shopping list** — adds each ingredient's shortfall as a
  shopping-list item with `source = recipe` (skips products already listed)

Backend-integrated end to end, with a dedicated Recipes page in the SPA.
```

### Edit B — Renumber the following sections and cross-refs

- Headings: Tasks & Chores §13→**§14**, Telegram §14→**§15**, UI Structure
  §15→**§16**, Localization §16→**§17**, Testing & Quality §17→**§18**, Roadmap
  §18→**§19**.
- All 10 `§18` cross-references (Roadmap) → `§19`. (Audit also re-checks no other
  in-prose section-number reference was missed.)

### Edit C — §12 Shopping List note (replace stale "not wired" line)

```markdown
> A `recipe` shopping-list source is **active** — a recipe's "add missing"
> action adds shortfall items as `source = recipe`. See §13 Recipes.
```

### Edit D — §16 (was §15) UI Structure

- Add to the Pages list: `Recipes (CRUD, fulfillment check, cook, add-missing to
  shopping list)`.
- Delete the "placeholder running on mock data … not backend-integrated" note.

### Edit E — §19 (was §18) Roadmap

- Remove the built **Recipes** v2 line. Leave all other roadmap items untouched
  (do not add new roadmap items).

### Edit F — `features_overview.md`

- Remove "recipe fulfillment UI" from **Postponed (v2+)**.
- Add recipes to the built-features list (mirroring the spec's feature framing).
- Leave "meal planning calendar" under Postponed (genuinely unbuilt).

## Out of scope (do NOT)

- Any code or config change.
- `design_decisions_log.md` §4 hosting topology — that's a separate issue (#63 / D2).
- Recipe design/plan docs under `docs/superpowers/{specs,plans}` — historical
  design records, not "as-built" docs.
- Adding new roadmap items or rewriting unrelated spec sections.

## Acceptance criteria

- §12, the new Recipes section, UI Structure, and Roadmap accurately reflect the
  shipped recipe feature (backend + actual integrated frontend).
- `features_overview.md` reconciled — no longer lists recipe fulfillment as
  postponed; no contradiction with the spec.
- No unbuilt feature described as built (claims verified against code).
- Section numbering is contiguous (1–19); all `§N` cross-references resolve.

## Verification (docs-only, no build step)

- `grep -niE 'not wired|placeholder|mock data' docs/home_erp_specification.md` —
  no recipe matches remain.
- `grep -n '§18' docs/home_erp_specification.md` — only intended (none should be
  the old Roadmap ref; Roadmap is now §19).
- Section headings read 1…19 contiguously.
- Read spec ↔ `features_overview.md` for mutual consistency on recipes.
