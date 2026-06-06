# M2 — Disambiguate duplicate NotFound exception `type` strings

**Date:** 2026-06-06
**Issue:** #61 (`architecture-review`, `tech-debt`) · Review ref `docs/reviews/2026-06-05-backend-architecture/README.md` § M2
**Severity:** Minor · **Effort:** S · **Area:** backend / error handling

## Problem

In the RFC 7807 error contract, `type` is the stable, machine-readable identity of *a kind of error* — a client keys off it to decide handling. Today two distinct exception classes emit the **same `type` at different HTTP statuses**, so the field identifies nothing uniquely:

- `Exception/Product/CategoryNotFoundException` → `type: CATEGORY_NOT_FOUND`, **400** — a bad `category_id` foreign-key reference in a submitted product payload.
- `Exception/Category/CategoryNotFoundException` → `type: CATEGORY_NOT_FOUND`, **404** — addressing a category resource that does not exist.

The same overload exists for Location (`Product/LocationNotFoundException` 400 vs `Location/LocationNotFoundException` 404).

The two *situations* are legitimately different (invalid FK reference in a payload vs. a missing addressed resource), so having two classes is correct. The defect is that the shared `type` undoes that distinction for the client.

**Severity is genuinely Minor:** the frontend references these `type` strings **zero** times (verified via grep over `frontend/src`). There is no live bug and no broken screen. This is error-contract hygiene — making the model self-consistent so a future consumer is never handed an ambiguous signal.

## Decision

Keep the two classes. Give the **payload-reference (400) variants** a distinct `type`, and move them to **422 Unprocessable Entity**. Leave the **resource-not-found (404) variants** completely unchanged.

### Why 422 (not "keep 400")

Two angles converge, and the cost is trivial:

1. **Semantic correctness.** A syntactically valid payload (a valid UUID) referencing a non-existent entity is the textbook definition of **422 Unprocessable Entity**, not 400 (malformed). 404 remains correct only for the *addressed-resource* case, which we are not touching.
2. **Consistency with the existing convention.** The codebase already says "a value in your payload is invalid → **422 `VALIDATION_ERROR`**" (the `MapRequestPayload` path in `App\EventListener\ApiExceptionListener::createValidationProblem`). A bad FK reference is the same conceptual failure caught one layer later — in a service lookup instead of a bean constraint. Keeping it at 400 means the same failure-class has two different statuses depending on *where* it is caught; 422 unifies it.

`VALIDATION_ERROR` and `INVALID_*_REFERENCE` co-existing at 422 is correct and clean: distinct error kinds, same status class — the exact inverse of the bug being removed (one `type`, two statuses).

These exception classes set their own status in their constructor (an `ApiException` carries its own `ApiProblem`; it does **not** flow through the listener's status-mapping logic), so moving to 422 is a single-constant change with no interaction risk against the `MapRequestPayload`/`MapQueryString` status handling noted in the listener.

### After the change

| Situation | Class | `type` | `title` | status |
|---|---|---|---|---|
| Bad FK in a submitted payload | `Exception/Product/CategoryNotFoundException` | `INVALID_CATEGORY_REFERENCE` | Invalid category reference | **422** |
| Bad FK in a submitted payload | `Exception/Product/LocationNotFoundException` | `INVALID_LOCATION_REFERENCE` | Invalid location reference | **422** |
| Addressed resource missing | `Exception/Category/CategoryNotFoundException` | `CATEGORY_NOT_FOUND` *(unchanged)* | Category not found | 404 *(unchanged)* |
| Addressed resource missing | `Exception/Location/LocationNotFoundException` | `LOCATION_NOT_FOUND` *(unchanged)* | Location not found | 404 *(unchanged)* |

Example response body:

```http
422 Unprocessable Entity
Content-Type: application/problem+json

{
  "title": "Invalid category reference",
  "type":  "INVALID_CATEGORY_REFERENCE",
  "code":  422,
  "id":    "<uuid>"
}
```

## Scope

### In scope
1. `src/Exception/Product/CategoryNotFoundException.php` — `type` → `INVALID_CATEGORY_REFERENCE`; `title` → `Invalid category reference`; `code` → `Response::HTTP_UNPROCESSABLE_ENTITY`.
2. `src/Exception/Product/LocationNotFoundException.php` — `type` → `INVALID_LOCATION_REFERENCE`; `title` → `Invalid location reference`; `code` → `Response::HTTP_UNPROCESSABLE_ENTITY`.
3. Update the backend tests that assert the old status/type on the payload-reference path (see Tests).

### Out of scope / do NOT
- Merge the two classes (semantics differ).
- Change the `Exception/Category/*` or `Exception/Location/*` (404) variants in any way.
- Build a broader error taxonomy or a shared "reference" base exception.
- Convert the FK existence check into a bean-validation constraint (the most-unified but heaviest option — rejected as over-engineering for a Minor item).
- Touch the frontend (no references exist).

## Throw sites (no code change required)

Both payload-reference classes are already thrown from the services; only the emitted status/type shift:

- `src/Service/ProductService.php` — `createProduct` / `updateProduct` throw both classes on a bad `category_id` / `default_location_id`.
- `src/Service/StockEntryService.php` — throws `Product/LocationNotFoundException` on a bad `location_id` (add/consume stock paths).

## Tests

### Must update (currently assert 400 + old `type`)
- `tests/Functional/Controller/Api/Internal/V1/ProductControllerTest.php`
  - `testCreateProductInvalidCategory` (~L350–361): assert **422** instead of `HTTP_BAD_REQUEST`; `type` → `INVALID_CATEGORY_REFERENCE`; `title` → `Invalid category reference`.
  - `testCreateProductInvalidLocation` (~L364–376): assert **422**; `type` → `INVALID_LOCATION_REFERENCE`; `title` → `Invalid location reference`.
  - Check for and update the **update-product** equivalents of these two cases if present (`ProductService::updateProduct` throws the same classes).

### Must remain unchanged (the 404 resource variants)
- `tests/Functional/Controller/Api/Internal/V1/CategoryControllerTest.php` (L120, L158 — `CATEGORY_NOT_FOUND` at 404).
- `tests/Functional/Controller/Api/Internal/V1/LocationControllerTest.php` (L123, L176 — `LOCATION_NOT_FOUND` at 404).

### Add (close a coverage gap)
- A functional test for the `StockEntryService` bad-`location_id` path asserting **422 `INVALID_LOCATION_REFERENCE`**. This contract path is currently unasserted; adding it locks the new behaviour. Place it in `StockControllerTest` alongside the existing stock add/consume cases.

## Acceptance criteria
- The two payload-reference variants emit `INVALID_CATEGORY_REFERENCE` / `INVALID_LOCATION_REFERENCE` at **422**, with matching titles.
- The 404 resource-not-found variants are byte-for-byte unchanged (`CATEGORY_NOT_FOUND` / `LOCATION_NOT_FOUND` at 404).
- No `type` string is shared across two statuses anywhere.
- Frontend confirmed unaffected (no references — already verified).
- `make lint` and `make test` are green.

## Verification

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint && make test

# Distinct codes at the 422 variants, NOT_FOUND only at the 404 variants:
grep -rn "CATEGORY_NOT_FOUND\|LOCATION_NOT_FOUND\|INVALID_CATEGORY_REFERENCE\|INVALID_LOCATION_REFERENCE" src/Exception
```

Frontend (confirmation only — expected to print nothing):

```bash
grep -rn "CATEGORY_NOT_FOUND\|LOCATION_NOT_FOUND" /home/pavel/projects/personal/hestia/frontend/src
```

## Hard-evaluate-first notes
- Re-confirm zero frontend references before/after (the key cross-area check) — done at design time, re-run at implementation.
- Read the `ProductControllerTest` cases before editing; do not blindly swap constants — confirm each asserts the payload-reference path, not a 404.
- Confirm no OpenAPI/error-code doc enumerates these `type` strings (none found at design time).
