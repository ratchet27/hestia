# M2 — NotFound Exception Type Disambiguation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the payload-reference NotFound exceptions a distinct `type` at HTTP 422, so no `type` string is shared across two different HTTP statuses.

**Architecture:** Two `Exception/Product/*NotFoundException` classes (thrown on a bad foreign-key reference in a submitted payload) change their RFC 7807 `type`, `title`, and status from `*_NOT_FOUND`/400 to `INVALID_*_REFERENCE`/422 — aligning with the codebase's existing "bad payload value → 422 `VALIDATION_ERROR`" convention. The `Exception/Category/*` and `Exception/Location/*` (404 addressed-resource) variants are left untouched. Each exception sets its own status in its constructor via the `ApiProblem` it carries, so the change is local to two files plus their tests.

**Tech Stack:** PHP 8 / Symfony, PHPUnit functional tests, `make lint` (rector → mago format → mago lint → mago analyze → phpstan) and `make test`. All backend commands run from `/home/pavel/projects/personal/hestia/backend`. PHP/composer run via `docker compose exec php`; `make test`/`make lint` wrap this — do not run `php` on the host.

**Spec:** `docs/superpowers/specs/2026-06-06-m2-notfound-exception-types-design.md` · **Issue:** #61

---

## File Structure

- `backend/src/Exception/Product/CategoryNotFoundException.php` — payload-reference category error. **Modify**: type/title/status.
- `backend/src/Exception/Product/LocationNotFoundException.php` — payload-reference location error. **Modify**: type/title/status.
- `backend/src/Exception/Category/CategoryNotFoundException.php` — addressed-resource (404). **Unchanged.**
- `backend/src/Exception/Location/LocationNotFoundException.php` — addressed-resource (404). **Unchanged.**
- `backend/tests/Functional/Controller/Api/Internal/V1/ProductControllerTest.php` — **Modify** two create-product error tests.
- `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php` — **Modify**: add one test locking the `StockEntryService` bad-location path at 422.

> Note: `ProductService::updateProduct` throws the same two classes, but there is **no** `testUpdateProductInvalidCategory/Location` test today — the create-path tests are the only assertions of the old behaviour. Adding update-path coverage is out of scope for this plan.

---

### Task 1: Category payload-reference → `INVALID_CATEGORY_REFERENCE` at 422

**Files:**
- Modify: `backend/tests/Functional/Controller/Api/Internal/V1/ProductControllerTest.php` (`testCreateProductInvalidCategory`, ~L349-362)
- Modify: `backend/src/Exception/Product/CategoryNotFoundException.php`

- [ ] **Step 1: Update the test to expect the new contract**

In `testCreateProductInvalidCategory`, replace the assertion block so status, title, and type all reflect the new 422 contract:

```php
    public function testCreateProductInvalidCategory(): void
    {
        $location = $this->createLocation();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => Uuid::v7(),
            'default_location_id' => $location->getId()
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);

        static::assertSame('Invalid category reference', $data['title']);
        static::assertSame('INVALID_CATEGORY_REFERENCE', $data['type']);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `make test` (or, faster: `docker compose exec php vendor/bin/phpunit --filter testCreateProductInvalidCategory`)
Expected: FAIL — response is still `400` / `CATEGORY_NOT_FOUND`, so the status assertion (422) fails first.

- [ ] **Step 3: Update the exception**

Edit `backend/src/Exception/Product/CategoryNotFoundException.php` — change the three fields in the `ApiProblem`:

```php
final class CategoryNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Invalid category reference',
            type: 'INVALID_CATEGORY_REFERENCE',
            code: Response::HTTP_UNPROCESSABLE_ENTITY,
            extraData: ['id' => (string) $id]
        ));
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose exec php vendor/bin/phpunit --filter testCreateProductInvalidCategory`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add src/Exception/Product/CategoryNotFoundException.php \
        tests/Functional/Controller/Api/Internal/V1/ProductControllerTest.php
git commit -s -m "fix(error-handling): emit INVALID_CATEGORY_REFERENCE at 422 for bad category ref (#61)"
```

---

### Task 2: Location payload-reference → `INVALID_LOCATION_REFERENCE` at 422

**Files:**
- Modify: `backend/tests/Functional/Controller/Api/Internal/V1/ProductControllerTest.php` (`testCreateProductInvalidLocation`, ~L364-377)
- Modify: `backend/src/Exception/Product/LocationNotFoundException.php`

- [ ] **Step 1: Update the test to expect the new contract**

In `testCreateProductInvalidLocation`, replace the assertion block:

```php
    public function testCreateProductInvalidLocation(): void
    {
        $category = $this->createCategory();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId(),
            'default_location_id' => Uuid::v7()
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);

        static::assertSame('Invalid location reference', $data['title']);
        static::assertSame('INVALID_LOCATION_REFERENCE', $data['type']);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec php vendor/bin/phpunit --filter testCreateProductInvalidLocation`
Expected: FAIL — response is still `400` / `LOCATION_NOT_FOUND`.

- [ ] **Step 3: Update the exception**

Edit `backend/src/Exception/Product/LocationNotFoundException.php`:

```php
final class LocationNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Invalid location reference',
            type: 'INVALID_LOCATION_REFERENCE',
            code: Response::HTTP_UNPROCESSABLE_ENTITY,
            extraData: ['id' => (string) $id]
        ));
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose exec php vendor/bin/phpunit --filter testCreateProductInvalidLocation`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add src/Exception/Product/LocationNotFoundException.php \
        tests/Functional/Controller/Api/Internal/V1/ProductControllerTest.php
git commit -s -m "fix(error-handling): emit INVALID_LOCATION_REFERENCE at 422 for bad location ref (#61)"
```

---

### Task 3: Lock the StockEntryService bad-location path at 422

`StockEntryService::add` throws `Product\LocationNotFoundException` on a non-existent `location_id`, but no functional test asserts it. Task 2 already changed that class, so this new test characterises and locks the now-422 contract on the stock path. (Product is validated before location in `/stocks/add` — `testAddStockFailsWithInvalidProduct` proves product is checked first — so use a valid product and a random location id.)

**Files:**
- Modify: `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php` (add a method next to `testAddStockFailsWithInvalidProduct`, ~L311-325)

- [ ] **Step 1: Add the new test**

Insert this method immediately after `testAddStockFailsWithInvalidProduct`:

```php
    public function testAddStockFailsWithInvalidLocation(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $response = $this->apiPost('/stocks/add', [
            'product_id' => (string) $product->getId(),
            'location_id' => '01936f00-0000-7000-8000-000000000000',
            'quantity' => 5
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);

        static::assertSame('INVALID_LOCATION_REFERENCE', $data['type']);
    }
```

- [ ] **Step 2: Run the test to verify it passes**

Run: `docker compose exec php vendor/bin/phpunit --filter testAddStockFailsWithInvalidLocation`
Expected: PASS (the behaviour is already in place from Task 2; this test locks it). If it instead returns 400/`LOCATION_NOT_FOUND`, Task 2 was not applied — fix that first.

- [ ] **Step 3: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php
git commit -s -m "test(stock): assert INVALID_LOCATION_REFERENCE on bad location_id (#61)"
```

---

### Task 4: Full gate + contract verification

**Files:** none (verification only).

- [ ] **Step 1: Confirm the 404 variants are untouched**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend
grep -rn "CATEGORY_NOT_FOUND\|LOCATION_NOT_FOUND\|INVALID_CATEGORY_REFERENCE\|INVALID_LOCATION_REFERENCE" src/Exception
```
Expected: `INVALID_CATEGORY_REFERENCE` only in `Product/CategoryNotFoundException.php`; `INVALID_LOCATION_REFERENCE` only in `Product/LocationNotFoundException.php`; `CATEGORY_NOT_FOUND` only in `Category/CategoryNotFoundException.php`; `LOCATION_NOT_FOUND` only in `Location/LocationNotFoundException.php`. No `type` string appears in two files.

- [ ] **Step 2: Confirm the frontend is unaffected**

Run:
```bash
grep -rn "CATEGORY_NOT_FOUND\|LOCATION_NOT_FOUND" /home/pavel/projects/personal/hestia/frontend/src
```
Expected: no output.

- [ ] **Step 3: Run the backend gate**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend
make lint && make test
```
Expected: lint clean, all tests green. `make lint` may auto-fix formatting — if it modifies files, stage exactly those files (never `git add -A`) and amend or add a `style(error-handling): mago format` commit.

- [ ] **Step 4: Final review**

Confirm: the two 404 variants and the frontend are byte-for-byte unchanged; the three behavioural commits are present; the working tree is clean.

---

## Self-Review

- **Spec coverage:** Category 422/type/title → Task 1; Location 422/type/title (Product path) → Task 2; StockEntryService coverage gap → Task 3; 404 variants untouched + frontend-unaffected + `make lint && make test` → Task 4. All spec sections covered. Update-product path explicitly noted as out of scope (no existing test).
- **Placeholder scan:** none — every code/test step shows full code and exact commands.
- **Type consistency:** `INVALID_CATEGORY_REFERENCE` / `INVALID_LOCATION_REFERENCE`, titles `Invalid category reference` / `Invalid location reference`, and `Response::HTTP_UNPROCESSABLE_ENTITY` (422) are used identically across exception and test edits.
