# Bound addStock quantity (W5, #59) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cap `addStock` quantity at 50 per request, enforced at the backend DTO and the frontend form, so a fat-fingered mass insert (e.g. `quantity: 100000`) is rejected.

**Architecture:** The backend `AddStockRequest` DTO gains a `LessThanOrEqual(50)` validation constraint (the authoritative check); because the `/stocks/add` endpoint maps its body via `new Model(type: AddStockRequest::class)`, the constraint auto-propagates to the OpenAPI schema and the generated client. The frontend `AddStockModal` gains a matching react-hook-form `max` rule with a localized message (defense-in-depth + clear UX). The one-row-per-unit storage model is unchanged.

**Tech Stack:** Symfony 7 (PHP 8.x, symfony/validator, nelmio/api-doc), PHPUnit functional tests; React + react-hook-form + react-i18next, Vitest + Testing Library.

---

## File Structure

- **Modify:** `backend/src/Request/AddStockRequest.php` — add the upper-bound constraint.
- **Modify:** `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php` — boundary tests.
- **Modify:** `frontend/src/i18n/locales/en.json`, `frontend/src/i18n/locales/ru.json` — new `addStock.quantityMax` key.
- **Modify:** `frontend/src/features/stock/components/AddStockModal.tsx` — `max` attribute + `max` rule.
- **Modify:** `frontend/src/features/stock/components/AddStockModal.test.tsx` — over-limit test.
- **Regenerate:** `frontend/src/api/generated/models/addStockRequest.ts` — picks up `maximum: 50` (no manual edit).

---

## Task 1: Backend — bound quantity at 50

**Files:**
- Modify: `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php` (add tests after `testAddStockWithBestBefore`, which ends around line 268)
- Modify: `backend/src/Request/AddStockRequest.php:20-21`

All backend commands run from `backend/` and use Docker for PHP. `make test` and `make lint` are defined in the backend Makefile.

- [ ] **Step 1: Write the failing tests**

Add these two methods to `StockControllerTest.php`, immediately after the `testAddStockWithBestBefore` method (after its closing `}` around line 268):

```php
    public function testAddStockRejectsQuantityAboveLimit(): void
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
            'location_id' => (string) $location->getId(),
            'quantity' => 51
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);

        static::assertSame('VALIDATION_ERROR', $data['type']);
    }

    public function testAddStockAcceptsQuantityAtLimit(): void
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
            'location_id' => (string) $location->getId(),
            'quantity' => 50
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame(50, $data['data']['created']);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `make test` (or, faster: `docker compose exec php vendor/bin/phpunit --filter 'testAddStockRejectsQuantityAboveLimit|testAddStockAcceptsQuantityAtLimit'`)
Expected: `testAddStockRejectsQuantityAboveLimit` FAILS — current code returns 201 (no upper bound), not 422. `testAddStockAcceptsQuantityAtLimit` passes (50 is already allowed).

- [ ] **Step 3: Add the constraint**

In `backend/src/Request/AddStockRequest.php`, change the `$quantity` block (lines 20-21):

```php
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(value: 50, message: 'Quantity must not exceed {{ compared_value }}.')]
        public int $quantity = 1,
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `docker compose exec php vendor/bin/phpunit --filter 'testAddStockRejectsQuantityAboveLimit|testAddStockAcceptsQuantityAtLimit'`
Expected: both PASS.

- [ ] **Step 5: Run the full backend gate**

Run: `make lint && make test`
Expected: all green. (`make lint` runs rector → mago format → mago lint → mago analyze → phpstan and may rewrite files — stage explicitly in the next step, do not `git add -A`.)

- [ ] **Step 6: Commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
git add src/Request/AddStockRequest.php tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php
git -C /home/pavel/projects/personal/hestia ci -m "fix(stock): cap addStock quantity at 50 (W5, #59)"
```

---

## Task 2: Frontend — matching cap + localized message

**Files:**
- Modify: `frontend/src/i18n/locales/en.json` (the `addStock` block, ends at the `"bestBefore"` line)
- Modify: `frontend/src/i18n/locales/ru.json` (the `addStock` block)
- Modify: `frontend/src/features/stock/components/AddStockModal.tsx:146-149`
- Modify: `frontend/src/features/stock/components/AddStockModal.test.tsx`

All frontend commands run from `frontend/` and use `bun` (never `npm`).

- [ ] **Step 1: Write the failing test**

Add this test inside the `describe("AddStockModal", …)` block in `AddStockModal.test.tsx`, after the `"validates required fields"` test. The component renders Russian copy in tests, so assert on the Russian message:

```tsx
  it("rejects quantity above the limit of 50", async () => {
    const onSubmit = vi.fn();

    const { user } = render(
      <AddStockModal
        products={products}
        locations={locations}
        preselectedProduct={products[0]}
        onSubmit={onSubmit}
        onClose={vi.fn()}
      />,
    );

    // Wait for location auto-fill so only quantity can block submit
    await waitFor(() => {
      const locationSelect = screen.getByLabelText(
        /Место/i,
      ) as HTMLSelectElement;
      expect(locationSelect.value).toBe("loc-1");
    });

    const quantityInput = screen.getByLabelText(/Количество/i);
    await user.clear(quantityInput);
    await user.type(quantityInput, "51");
    await user.click(screen.getByRole("button", { name: /Добавить/i }));

    await waitFor(() => {
      expect(screen.getByText(/Максимум 50/i)).toBeInTheDocument();
    });
    expect(onSubmit).not.toHaveBeenCalled();
  });
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `bun run test:run src/features/stock/components/AddStockModal.test.tsx`
Expected: the new test FAILS — there is no `max` rule yet, so `onSubmit` is called and "Максимум 50" is never rendered.

- [ ] **Step 3: Add the i18n keys**

In `frontend/src/i18n/locales/en.json`, in the `"addStock"` object, add a `quantityMax` line after `"quantityMin"`:

```json
    "quantityMin": "Minimum 1",
    "quantityMax": "Maximum 50",
    "bestBefore": "Best before"
```

In `frontend/src/i18n/locales/ru.json`, in the `"addStock"` object, add after `"quantityMin"`:

```json
    "quantityMin": "Минимум 1",
    "quantityMax": "Максимум 50",
    "bestBefore": "Годен до"
```

- [ ] **Step 4: Add the max rule to the modal**

In `frontend/src/features/stock/components/AddStockModal.tsx`, update the quantity `<input>` (lines 143-153). Add `max="50"` to the element and a `max` rule to `register`:

```tsx
              <input
                id="add-quantity"
                type="number"
                min="1"
                max="50"
                {...register("quantity", {
                  required: t("addStock.quantityRequired"),
                  min: { value: 1, message: t("addStock.quantityMin") },
                  max: { value: 50, message: t("addStock.quantityMax") },
                  valueAsNumber: true,
                })}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `bun run test:run src/features/stock/components/AddStockModal.test.tsx`
Expected: all tests in the file PASS, including the new one.

- [ ] **Step 6: Run the frontend gate**

Run: `bun run check && bun run test:run`
Expected: all green. (`bun run check` verifies formatting/lint/types; if it reports fixable issues, run `bun run check:write` then re-run `bun run check`.)

- [ ] **Step 7: Commit**

```bash
cd /home/pavel/projects/personal/hestia/frontend
git -C /home/pavel/projects/personal/hestia add \
  frontend/src/i18n/locales/en.json \
  frontend/src/i18n/locales/ru.json \
  frontend/src/features/stock/components/AddStockModal.tsx \
  frontend/src/features/stock/components/AddStockModal.test.tsx
git -C /home/pavel/projects/personal/hestia ci -m "fix(stock): cap quantity input at 50 in add-stock form (W5, #59)"
```

---

## Task 3: Regenerate the API client

The generated client should reflect the new `maximum: 50` that nelmio now emits for `AddStockRequest.quantity`. This requires the backend running so the OpenAPI spec can be fetched.

**Files:**
- Regenerate: `frontend/src/api/generated/models/addStockRequest.ts` (and any other generated churn)

- [ ] **Step 1: Ensure the backend is running**

Run (from `backend/`): `docker compose ps`
Expected: the PHP/web service is up. If not, start it: `docker compose up -d`.

- [ ] **Step 2: Regenerate**

Run (from `frontend/`):

```bash
cd /home/pavel/projects/personal/hestia/frontend
NODE_TLS_REJECT_UNAUTHORIZED=0 bun run generate-api
```

- [ ] **Step 3: Verify the cap landed and nothing else drifted**

Run:

```bash
git -C /home/pavel/projects/personal/hestia diff --stat frontend/src/api/generated
grep -n "maximum\|quantity" src/api/generated/models/addStockRequest.ts
```

Expected: `addStockRequest.ts` shows a `maximum: 50` (JSDoc `@maximum 50`) on `quantity`. If unrelated generated files changed (spec drift from other work), review the diff and keep only the intended change; if the churn is broad/unrelated, discard it and note it in the PR — the cap is already enforced at runtime by Tasks 1 and 2.

- [ ] **Step 4: Re-run the frontend gate**

Run: `bun run check`
Expected: green.

- [ ] **Step 5: Commit (only if files changed)**

```bash
git -C /home/pavel/projects/personal/hestia add frontend/src/api/generated
git -C /home/pavel/projects/personal/hestia ci -m "chore(api): regenerate client for addStock quantity cap (W5, #59)"
```

---

## Final Verification

```bash
# backend
cd /home/pavel/projects/personal/hestia/backend
make lint && make test
grep -n "LessThanOrEqual" src/Request/AddStockRequest.php

# frontend
cd /home/pavel/projects/personal/hestia/frontend
bun run check && bun run test:run
grep -n "quantityMax" src/i18n/locales/en.json src/i18n/locales/ru.json
```

**Acceptance:**
- `AddStockRequest.quantity` rejects values above 50 with a 422 `VALIDATION_ERROR`; values 1..50 still succeed.
- The add-stock form blocks `> 50` with "Maximum 50" / "Максимум 50" before submitting.
- `maximum: 50` is present in the generated client model for `quantity`.
- Backend and frontend gates green.
