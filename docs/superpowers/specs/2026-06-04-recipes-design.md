# Recipes (v2) — Design

**Date:** 2026-06-04
**Status:** Implemented (2026-06)
**Scope:** Roadmap §18 "Recipes: fulfillment check, shopping-list generation, stock
consumption." Wires up the existing placeholder `RecipesPage` and the dormant
`ShoppingListSource::RECIPE` enum value.

---

## 1. Decision: build native, do not integrate Mealie

The valuable half of "recipes" for Hestia is **fulfillment** — "can I cook this
with what I have *right now*, what's missing, consume stock when I cook." A
third-party recipe manager (Mealie/Tandoor/Paprika) cannot do this: it has no
visibility into Hestia stock. Recipe *management* (storage, web-import, photos,
meal-plan calendar) is a solved external problem and explicitly out of scope per
the spec's "not a product or platform / low friction" stance.

Usage reality (confirmed): ~80% rotating family staples (no instructions needed),
~20% newer dishes the user wants to jot the method for. This is overwhelmingly a
fulfillment feature with a light secondary "remember how to make it" need.

Integrating Mealie was rejected: it adds an external service, an auth/sync layer,
and fuzzy free-text ingredient→product matching, **while still requiring us to
build the entire fulfillment engine** for the 80% case. A future "import from
Mealie" adapter remains *possible* but is not built; the data model below leaves
room for it without a rewrite.

---

## 2. Data model

```
Recipe
  id              uuid (pk)
  name            string, required
  instructions    text, nullable        ← the 20% "remember the method" case
  source_url      string, nullable
  created_at      datetime_immutable

RecipeIngredient
  id              uuid (pk)
  recipe          FK → Recipe (cascade delete; orphanRemoval)
  product         FK → Product (RESTRICT on delete — see §5)
  required_count  int, >= 1             ← whole stock entries (no fractional amount)
  consume_on_cook bool, default true    ← true = single-use perishable (removed on cook)
                                          false = multi-use pantry staple (checked only)
```

Deliberately **excluded** (YAGNI / model fit): photos, servings/portions,
per-ingredient units, fractional amounts, ordering. Count-only matches Hestia's
discrete-entry stock model (on-hand = number of entries).

`ShoppingListSource::RECIPE` already exists — no enum change.

---

## 3. The partial-consumption problem and its resolution

Hestia stock is **discrete entries with no amount column** and, by deliberate
design, **no "opened" logic**. So `1 pack of pasta = 1 entry`; the system cannot
represent "half a pack." A naive "Cook consumes the pasta entry" is wrong for
multi-use staples (one pack survives many dinners) but correct for single-use
perishables (the 2 chicken filets are gone after one cook).

**Resolution — per-ingredient `consume_on_cook` flag**, set once when defining a
recipe:

- 🔥 `consume_on_cook = true` — single-use. Cook removes `required_count` entries
  FIFO. (chicken, eggs, fresh veg bought for this meal)
- 📦 `consume_on_cook = false` — multi-use pantry staple. Cook verifies it is in
  stock but **never decrements**. (pasta, rice, oil, salt, spices)

Residual imprecision (a 🔥 ingredient whose entry was really a multi-pack) is
accepted under the spec's "low friction > full accuracy / approximate data is
acceptable" principle, and corrected via the normal inventory-correction flow.
Reopening fractional/opened-amount tracking was rejected as a large architectural
change for marginal accuracy.

---

## 4. Fulfillment logic (read-only, computed live)

Computed per request from live stock — never cached.

For each ingredient:
- `in_stock` = total stock entries for that product **across all locations**
  (`StockEntryRepository::countByProduct`)
- `has_enough` = `in_stock >= required_count`
- `shortfall` = `max(0, required_count - in_stock)`

Recipe `cookable` = every ingredient has `has_enough`.

Inactive product referenced by an ingredient: still listed, surfaced with an
`inactive` flag; does not break fulfillment computation.

---

## 5. API — `/api/internal/v1/recipes`

Follows existing controller/service/request/response conventions
(`RecipeController` → `RecipeService`, request DTOs in `Request/`, response DTOs
in `Response/Recipe/`).

- `GET /recipes` — list. Each recipe includes computed fulfillment per ingredient
  (`in_stock / required_count / has_enough`, product brief, `consume_on_cook`,
  `inactive`) and an overall `cookable` flag.
- `GET /recipes/{uuid}` — single recipe with fulfillment.
- `POST /recipes` — create (name, optional instructions, optional source_url,
  ingredient list of `{product_id, required_count, consume_on_cook}`).
- `PUT /recipes/{uuid}` — update (full ingredient-list replace via orphanRemoval).
- `DELETE /recipes/{uuid}` — delete recipe (ingredients cascade).
- `POST /recipes/{uuid}/cook` — **strict**. Returns **409** if not `cookable`.
  Otherwise, in one transaction: for each ingredient with
  `consume_on_cook = true`, consume `required_count` entries via **cross-location
  FIFO** (see below); `consume_on_cook = false` ingredients untouched. Dispatch
  `StockChangedMessage` per consumed product so below-min auto-add still fires.
- `POST /recipes/{uuid}/add-missing-to-shopping-list` — for each ingredient with
  `shortfall > 0`, **upsert** a shopping-list item `(product, amount = shortfall,
  source = RECIPE)`. Because there is at most one shopping-list item per product
  (`findByProduct`), if an active item for the product already exists, leave it
  (no duplicate, no amount bump). Returns the set of items added/skipped.

### Required backend addition: cross-location FIFO consume

The existing `StockEntryService::consumeStock` and
`StockEntryRepository::findForFifoConsumption(productId, locationId, qty)` are
**location-scoped**. Recipe ingredients have no location, and fulfillment counts
across all locations, so Cook needs a **global FIFO** consume:

- New repo method `findForFifoConsumptionAcrossLocations(Uuid $productId, int $qty)`
  ordered by `best_before ASC NULLS LAST, created_at ASC` regardless of location.
- New service method (in `StockEntryService` or a dedicated `RecipeCookService`)
  that consumes N entries globally and dispatches `StockChangedMessage`.

`RecipeService::cook` orchestrates: validate cookable → for each 🔥 ingredient,
call the global FIFO consume → single flush/transaction.

---

## 6. Frontend

Replace the mock-data `RecipesPage` (currently `src/data/hooks` + `src/data/mocks`,
with a known number-id-vs-UUID mismatch) with the real generated API client
(regenerate via `bun run generate-api`).

- **Recipe cards** — essentially the existing placeholder: status badge
  (Можно приготовить / Не хватает N), ingredient rows showing `in_stock /
  required_count` with ✓/✗, **Приготовить** button (disabled unless `cookable`),
  **Добавить недостающее** button.
- **Create / edit form** — name, optional instructions (textarea), optional
  source URL, and an ingredient-rows editor: product picker + count stepper +
  a 🔥/📦 consume toggle per row.
- Remove `src/data/mocks` recipe data and the mock `useRecipes` hook once wired.
- RU-primary i18n keys throughout (app primary language is Russian — "Гестия").

---

## 7. Edge cases & resolved decisions

| Case | Decision |
|------|----------|
| Cook when not all green | Blocked, 409. No "cook anyway" in v1. |
| Add-missing amount | Shortfall only (`required - in_stock`), not full `required`. |
| Add-missing when item already on list | Upsert: skip if present (one item/product); no duplicate. |
| Delete a product used by a recipe | **Restrict** — block the delete (consistent, safe). |
| Inactive product in a recipe | Listed + flagged `inactive`; fulfillment still computes. |
| `consume_on_cook` ingredient as multi-pack | Accepted imprecision; fix via inventory correction. |
| Consume location | Cross-location global FIFO (recipes are location-agnostic). |

---

## 8. Testing

- **Backend** (PHPUnit, Foundry factories, `ResetDatabase`; gate `make lint`):
  - Fulfillment math: `in_stock` across locations, `has_enough`, `shortfall`,
    `cookable` aggregation.
  - Cook: strict 409 when not cookable; consumes only 🔥 ingredients; 📦
    untouched; cross-location FIFO order (earliest best-before first); dispatches
    `StockChangedMessage`; transactional (all-or-nothing).
  - Add-missing: adds shortfall with `source=RECIPE`; no duplicate when item
    already present.
  - Product delete restricted when referenced by a recipe ingredient.
  - Recipe CRUD incl. ingredient-list replace via orphanRemoval.
- **Frontend** (Vitest + MSW + Testing Library; gate `bun run check` +
  `bun run test:run`):
  - Card states (cookable vs missing), Cook disabled logic.
  - Add-missing triggers the correct call.
  - Recipe form validation (name required, ≥1 ingredient, count ≥ 1).

---

## 9. Out of scope (future, not now)

- Import from Mealie / any external recipe source (model leaves room; adapter not built).
- Photos, servings, fractional/opened-amount consumption.
- Meal-plan calendar, recipe discovery/browse-by-category.
- "Cook anyway" partial cooking.
