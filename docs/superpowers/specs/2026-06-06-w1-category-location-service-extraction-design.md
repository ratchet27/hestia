# W1 — Extract Category & Location CRUD into services (and fix the racy uniqueness 500)

**Issue:** #55 (architecture-review, tech-debt) · **Review ref:** `docs/reviews/2026-06-05-backend-architecture/README.md` § W1
**Severity:** High · **Effort:** S · **Area:** backend / categories + locations

## Problem

`CategoryController` and `LocationController` are the only two domains (of nine) that perform CRUD
inline instead of delegating to a service. Two consequences:

1. **A real correctness defect.** `assertNameAvailable()` hand-rolls a `findOneBy` name-uniqueness
   check that re-implements the entity's `#[UniqueEntity]`. It is racy: two near-simultaneous creates
   both pass the `SELECT`, the second hits the DB unique constraint, and because the controller never
   translates that `UniqueConstraintViolationException`, it surfaces as an **unmapped 500** instead of
   the clean **409** the `Product` path produces.
2. **Convention drift.** Name-availability, the in-use guard, and transaction control
   (`persist`/`flush`) live in the HTTP layer for these two domains only — harder to find, and outside
   Infection's `src/Service` mutation scope (the W4 / #57 tie-in).

### The actual race mechanism (why the obvious fixes don't fix it)

Both the hand-rolled `findOneBy` **and** Symfony's `#[UniqueEntity]` validator run a `SELECT` *before*
the write, so both leave the same check-then-act window open. The **only** thing that closes the race
is catching `UniqueConstraintViolationException` at `flush()` and converting it to a 409. Any pre-check
is a fast-path/UX nicety, not a correctness guard. There is currently no `UniqueConstraintViolationException`
handling anywhere in the codebase — this is net-new.

## Decision: catch-only, preserve the 409 contract

Enforce uniqueness through a **single authority — the database unique constraint** — and translate its
violation at flush:

```php
$this->em->persist($category);   // create only
try {
    $this->em->flush();
} catch (UniqueConstraintViolationException) {
    throw new CategoryNameTakenException($request->name);   // 409 CATEGORY_NAME_TAKEN — unchanged
}
```

- **No pre-check.** The hand-rolled `findOneBy` duplicate of `#[UniqueEntity]` is deleted.
- `#[UniqueEntity]` **stays on the entity** — documentation of the invariant and protection for any
  non-service write path; the service does not invoke the validator for uniqueness.
- **Contract preserved exactly:** duplicate name → 409 `CATEGORY_NAME_TAKEN` (and `LOCATION_NAME_TAKEN`).
  The race that previously produced 500 now produces the same 409.

### Why this is the clean choice (not just the small one)

- **Single source of truth:** the DB constraint is the only guard that holds under concurrency. Keeping
  a racy approximation alongside it is the unclean option; deleting it is the clean one.
- **Correct HTTP semantics:** a duplicate name is a *conflict with existing state* → **409**, which is
  what the existing `type` already says. Switching to 422 `VALIDATION_ERROR` (ProductService's
  convention) would be a step away from correct semantics — the payload is well-formed, it just collides.
- **Honest caveat:** this uses an exception for a not-strictly-exceptional case. Accepted: the pre-check
  did the same `SELECT` anyway, only earlier and unreliably; on a single-household home server real
  concurrency is near-zero, so a pre-flight `SELECT` on every create buys nothing.

Frontend coupling was checked: `frontend/src` references none of `CATEGORY_NAME_TAKEN`,
`LOCATION_NAME_TAKEN`, `CATEGORY_IN_USE`, `LOCATION_IN_USE` — so the client is unaffected regardless.

## Components

Two new concrete services mirroring the existing convention (e.g. `ShoppingListService`). **No shared
base class / generic CRUD abstraction** — explicitly out of scope.

### `App\Service\CategoryService`
DI: `EntityManagerInterface`, `CategoryRepository`, `ProductRepository`

| Method | Behavior |
|--------|----------|
| `list(): Category[]` | `categoryRepository->findAllOrderedByName()` |
| `create(CreateCategoryRequest): Category` | new + `persist` + guarded `flush` (409 on violation) |
| `update(Uuid, UpdateCategoryRequest): Category` | load-or-`CategoryNotFoundException` (404); rename + guarded `flush` **only when the name actually changed** |
| `delete(Uuid): void` | load-or-404; `usageCount > 0` → `CategoryInUseException` (409); else `remove` + `flush` |
| `usageCount(Category): int` | **public**; `productRepository->count(['category' => $c])` |

### `App\Service\LocationService`
DI: `EntityManagerInterface`, `LocationRepository`, `ProductRepository`, `StockEntryRepository`

Same shape; `usageCount` =
`productRepository->count(['defaultLocation' => $l]) + stockEntryRepository->count(['location' => $l])`.

### `usageCount` placement

`usageCount()` is a **public service method**. The controllers' `usage_count` response field is built by
a small private `toResponse(Category): CategoryListItemResponse` in the controller that calls
`$service->usageCount($entity)`. This keeps the counting logic inside `src/Service` (Infection scope,
W4 tie-in) and out of the HTTP layer, while the controller only assembles the DTO. (No entity leak
concern: the controller already holds the entity returned by the service.)

## Controller slimming

`CategoryController` / `LocationController` become thin delegators:

- Constructor injects the **service only** — drop `CategoryRepository`/`LocationRepository` and
  `EntityManagerInterface`.
- Each action delegates to the service; `list`/`create`/`update` map via the private `toResponse`.
- Remove `assertNameAvailable`, the inline `usageCount` (Product/StockEntry counting), and every
  `persist`/`flush`.
- `#[OA\Response]` annotations stay as-is (409 name-taken, 409 in-use, 404, 422 from DTO validation) —
  the contract is preserved, so no annotation changes.

## Testing

Two layers — both, since they cover different things and don't conflict:

### Functional (end-to-end, real DB) — `tests/Functional/Controller/...`
- **Existing `CategoryControllerTest` / `LocationControllerTest` pass unchanged** — they already assert
  409 `*_NAME_TAKEN` for duplicate create and rename-to-existing, in-use delete → 409, not-found → 404.
  These prove the contract is preserved through the full HTTP stack after the refactor.

### Unit (isolated, mocked collaborators) — `tests/Unit/Service/`
New `CategoryServiceTest` / `LocationServiceTest` with the `EntityManagerInterface` and repositories
mocked, so the moved logic is exercised fast and in isolation (and lives in `src/Service`, inside
Infection's scope — the W4 tie-in):
- **Race translation (the key new case):** stub `em->flush()` to throw `UniqueConstraintViolationException`
  → assert the service throws `CategoryNameTakenException` (409). This is exactly the translation that was
  missing; the mocked EM lets us prove it deterministically without simulating two live transactions
  (true concurrency still cannot be unit-simulated — the PR will note that).
- `delete` with `usageCount > 0` (stub repo count) → `CategoryInUseException` (409); with `0` →
  `remove` + `flush` called.
- `update` no-op rename (same name) → no `flush`, no 409 against itself.
- `usageCount` sums the right repository counts (Location: products + stock entries).

## Acceptance criteria

- Neither controller contains `persist`, `flush`, `usageCount`, or `assertNameAvailable`; both delegate
  to services. (`grep` clean — see Verification.)
- Name uniqueness is enforced via a single mechanism (the DB constraint translated at flush); no
  hand-rolled `findOneBy` duplicate of `#[UniqueEntity]`.
- Duplicate-name create/rename returns 409 `*_NAME_TAKEN`, **never** an unmapped 500.
- API contract otherwise unchanged (routes, response shapes, statuses).
- `make lint` + `make test` green.

## Verification

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint && make test
grep -n "persist\|flush\|assertNameAvailable\|usageCount" \
  src/Controller/Api/Internal/V1/CategoryController.php \
  src/Controller/Api/Internal/V1/LocationController.php   # expect: no hits
grep -rn "UniqueConstraintViolationException" src/Service                 # expect: in both new services
```

## Out of scope (do NOT)

- Generic base-CRUD abstraction.
- Any API route / response-shape / status change (except the race 500 → 409).
- Removing `#[UniqueEntity]` from the entities.
- Frontend changes.
- Infection config edits (that is W4 / #57).

## Hard-evaluate-first notes (from the issue)

- Keep the `#[OA\Response(...)]` 409/422 annotations truthful as status handling moves into services.
- The `#[UniqueEntity]` message vs the hand-rolled `*NameTakenException` message — reconcile to one
  source of truth (the exception is the API-facing message; `#[UniqueEntity]`'s message is now
  internal/secondary).
- Read existing controller tests deliberately; none should still expect a 500 on the race.
