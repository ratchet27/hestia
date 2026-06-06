# M1 — One documented response-mapping convention

**Date:** 2026-06-06
**Issue:** [#60](https://github.com/ratchet27/hestia/issues/60) — *Entity→response mapping is done three inconsistent ways* (`architecture-review`, `tech-debt`)
**Review ref:** `docs/reviews/2026-06-05-backend-architecture/README.md` § M1
**Severity:** Minor · **Effort:** S–M · **Area:** backend / response mapping

## Problem

Entities are turned into response DTOs by several different mechanisms with no documented default, so a developer must learn multiple patterns and the worst case (a hand-built response **array** inside a controller) mixes HTTP-response shaping into controller code. This is a **consistency/maintainability** concern only — no rule is violated and there is no correctness bug.

## Verified current state (not assumed)

A thorough sweep of `backend/src` (2026-06-06) found the following — note this corrects two claims in the original review:

- **The "entity leak" is stale.** No controller action returns a raw entity for the serializer to map. Every controller-boundary crossing already returns a Response DTO (or an array of them). This part of the issue no longer applies.
- **The split is ~7 / ~10 / a few**, and it is *not* arbitrary:
  - **ObjectMapper `#[Map]` (7 DTOs):** `UserResponse`, `BarcodeResponse`, `CategoryResponse`, `ChoreResponse`, `LocationResponse`, `ProductResponse`, `TaskResponse`. All are **flat** entity→DTO maps with no computed fields.
  - **Hand-written mapping (~10):** private `toResponse()` / `mapEntryToResponse()` methods and one static `fromEntity()`. These exist because they do work ObjectMapper cannot do cleanly — computed fields (`days_until_expiry` via the household calendar), aggregates, `Y-m-d` date-only formatting, nested briefs.
  - **Pure inline array (the wart):** `StockController::add` (`StockController.php:178-181`) returns a raw `['id'=>…, 'best_before'=>…]` array — not a DTO at all.
- **A contract bug at the wart:** the OpenAPI annotation on `add()` (`StockController.php:163`) declares each entry as a full `StockEntryResponse`, but the runtime response is only `{id, best_before}`. The documented type and the real response already disagree, and the generated frontend client currently reflects the wrong (fuller) type.
- **Date-format landmine:** `src/Serializer/DateTimeImmutableNormalizer.php` emits `DateTimeInterface::ATOM` (`2023-01-15T10:30:45+00:00`) for `DateTimeImmutable` fields, but date-only fields such as `best_before` are deliberately formatted `Y-m-d` in the hand-written maps. **Any migration must preserve `Y-m-d` for those fields exactly.**
- **`backend/AGENTS.md` has no response-mapping section.**
- **`ShoppingItemResponse::fromEntity()` already conforms** to the convention below (computed display name, static factory on the DTO).

## The convention (the "hybrid rule")

There are **three legitimate categories** plus **one thing to never do**. This rule is documented in a new "Response mapping" subsection of `backend/AGENTS.md`:

| When | Mechanism | Examples |
|------|-----------|----------|
| **Flat** entity→DTO, no computed fields | Symfony ObjectMapper `#[Map]` | Category, Location, Task, Chore, Barcode, Product, User |
| Entity→DTO **with computed/derived fields** | static `DTO::fromEntity(Entity, …scalars)` factory **on the DTO** | StockEntryResponse, ExpiringEntryResponse, ProductBriefResponse, ShoppingItemResponse *(already conforms)* |
| DTO **assembled from queries / aggregates / multiple sources** (no single source entity) | built in the **service** — no `#[Map]`, no factory | StockSummaryResponse, ProductSummaryResponse, LocationQuantityResponse, ConsumeResultResponse |
| ❌ **never** | response **arrays** built inline in a controller | `StockController::add` (the wart) |

Notes:
- Constructing a nested DTO by constructor inside a factory or service is **composition**, not a competing mapping mechanism. A given DTO class uses exactly one of the first three mechanisms.
- A `fromEntity()` factory **must stay pure**: it takes the entity plus any pre-computed scalars and constructs the DTO. It must not depend on a domain service. When a derived value needs a collaborator (e.g. `days_until_expiry` needs `HouseholdCalendar`), the **service computes the scalar** and passes it in: `StockEntryResponse::fromEntity(StockEntry $e, ?int $daysUntilExpiry)`. This keeps the factory trivially unit-testable with no mocks. (Rejected alternative: passing the `HouseholdCalendar` into the factory — couples the response layer to a domain service.)

### Why scope includes normalizing the Stock cluster (and where it stops)

The Stock-entry maps are **true single-entity→DTO maps with a computed field**, and `ProductBriefResponse` is hand-built **identically in three places** (`mapEntryToResponse`, `getExpiringEntries`, `getStockSummary`). Co-locating these as `fromEntity()` factories removes the scattered private `mapEntryToResponse`, dedupes the 3× `ProductBriefResponse` construction, and makes them unit-testable in isolation — a real, low-risk win that is the heart of M1.

It **stops at the aggregate builders** (`ProductSummaryResponse`, `StockSummaryResponse`, `LocationQuantityResponse`): these assemble from SQL rows plus additional queries and have **no single source entity**. They are category 3 and stay in the service. This boundary is what keeps the change from creeping into a big-bang rewrite (which the issue explicitly forbids).

## Scope

### In scope
1. Document the hybrid rule in `backend/AGENTS.md` (new "Response mapping" subsection).
2. **Stock cluster factories** (category 2):
   - `ProductBriefResponse::fromEntity(Product): self` — pure.
   - `StockEntryResponse::fromEntity(StockEntry $e, ?int $daysUntilExpiry): self` — uses `ProductBriefResponse::fromEntity`, builds nested `LocationResponse` by constructor, `best_before` stays `Y-m-d`, `created_at` unchanged.
   - `ExpiringEntryResponse::fromEntity(StockEntry $e, int $daysUntilExpiry): self` — same shape; `best_before` non-null per the `findExpiring` query.
   - Refactor `StockEntryService::getEntries/getEntry/getExpiringEntries` to compute the scalar via `householdCalendar` and call the factories; **delete the private `mapEntryToResponse`** and the inline closure in `getExpiringEntries`.
3. **Wart fix** (`StockController::add`):
   - New DTO `AddedStockEntryResponse(Uuid $id, ?string $best_before)` with `::fromEntity(StockEntry): self` (`best_before` = `Y-m-d`).
   - `add()` returns `array_map(AddedStockEntryResponse::fromEntity(...), $entries)` — **identical JSON shape** `{id, best_before}`, no runtime change.
   - Correct the OpenAPI annotation (`StockController.php:163`) from `StockEntryResponse` to `AddedStockEntryResponse`; regenerate the frontend client so generated types match the real response (they are currently wrong).

### Out of scope — listed for opportunistic migration (do NOT do here)
- `RecipeService::toResponse` → `RecipeResponse` / `RecipeIngredientResponse` (stock-aware fulfillment computation — a separate concern).
- `CategoryListItemResponse` / `LocationListItemResponse` controller `toResponse()` methods (flat list-item projections; minor).
- The aggregate builders are **not** outliers — they are correct under category 3 and stay as-is.

### Explicitly NOT doing
- No big-bang migration of every response in one PR.
- No change to any response JSON shape / API contract.
- No bespoke mapping abstraction.

## Components & data flow

- **DTO factories** (`src/Response/Stock/*`): pure static `fromEntity()` constructors. Depend only on the entity + scalars. Independently unit-testable.
- **`StockEntryService`**: owns `HouseholdCalendar`; computes `days_until_expiry`; calls factories. No longer holds mapping logic for single entries.
- **`StockController::add`**: returns DTOs, never arrays.
- **Date formatting** is unchanged: `Y-m-d` for date-only fields, ATOM via the normalizer for `DateTimeImmutable` fields (`created_at`).

## Error handling

No new error paths. Null-safety is preserved exactly: `best_before` may be null on `StockEntryResponse`/`AddedStockEntryResponse`; `days_until_expiry` is null when `best_before` is null (the service applies this rule before calling the factory).

## Testing

- **Functional tests guard shape drift:** `tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php` asserts exact JSON for `summary`, `listEntries`, `add` (`data.entries[].id`, `data.entries[].best_before`), `consume`, `updateEntry`, `expiring`. Run unchanged — must stay green, proving no shape change.
- **New unit tests** for each new factory (pure, no container): assert field-for-field output including `best_before` = `Y-m-d`, null-safety, and `days_until_expiry` passthrough.
- **Frontend verification gate:** confirm the SPA reads only `id` / `best_before` from the add response before regenerating the client (it cannot read more — the runtime never returned more). Regenerate and run `bun run check`.

## Acceptance criteria

- A single default mapping convention is documented in `backend/AGENTS.md`.
- No controller builds a response array inline (`StockController::add` converted to DTOs).
- The Stock-entry single-entity maps use co-located `fromEntity()` factories; the private `mapEntryToResponse` is gone.
- Aggregate DTOs remain service-assembled (unchanged).
- **No API response JSON shape changes** (functional tests confirm identical JSON).
- The `add` OpenAPI annotation matches the real response; frontend client regenerated and `bun run check` green.
- `make lint` && `make test` green.

## Verification

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint && make test
# no inline response arrays remain in the controller:
grep -n "'id' =>" src/Controller/Api/Internal/V1/StockController.php   # expect: no match
# factories exist:
grep -rn "public static function fromEntity" src/Response/Stock/

cd /home/pavel/projects/personal/hestia/frontend
bun run check
```

## Hard-evaluate notes (verify, don't trust)

- Keep `best_before` formatting at `Y-m-d`; `created_at` stays ATOM via `DateTimeImmutableNormalizer`.
- The original review's "entity leak" claim is stale (verified) — do not chase it.
- Lean on the functional tests' exact-JSON assertions to catch any drift.

## References

Review doc § M1 · book Ch.4 (ACWA — controllers translate results out; reads return DTOs, not entities).
