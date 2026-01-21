# Shopping List Fixes Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix three shopping list issues: (1) no refresh on page visit, (2) AUTO→MANUAL conversion on amount change, (3) deficit tracking current value instead of historical max.

**Architecture:** Frontend fix adds query invalidation on mount + optional polling. Backend fixes modify `ShoppingListService` to convert source on update and track current deficit.

**Tech Stack:** React Query (frontend), Symfony/PHP (backend), PHPUnit (tests)

---

## Task 1: Frontend - Refresh shopping list on page visit

**Files:**
- Modify: `frontend/src/features/shopping/ShoppingPage.tsx`

**Step 1: Add useEffect to invalidate query on mount**

In `ShoppingPage.tsx`, add the import and effect:

```tsx
import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { queryKeys } from "@/api/queries/keys";
```

Inside the component, after the hooks:

```tsx
export function ShoppingPage(): React.ReactElement {
  const queryClient = useQueryClient();
  const [editingItem, setEditingItem] = useState<ShoppingItemResponse | null>(
    null,
  );

  const { data: items = [], isLoading } = useShoppingList();
  const addMutation = useAddShoppingItem();
  const updateMutation = useUpdateShoppingItem();
  const deleteMutation = useDeleteShoppingItem();

  // Refetch on every page visit
  useEffect(() => {
    queryClient.invalidateQueries({
      queryKey: queryKeys.shoppingList.list(),
    });
  }, [queryClient]);

  // ... rest unchanged
```

**Step 2: Run frontend check**

Run: `cd /home/pavel/projects/hestia/frontend && bun run check`
Expected: PASS (no type errors)

**Step 3: Run frontend tests**

Run: `cd /home/pavel/projects/hestia/frontend && bun run test:run`
Expected: All tests pass

**Step 4: Commit**

```bash
git add frontend/src/features/shopping/ShoppingPage.tsx
git commit -s -m "fix(frontend): refresh shopping list on page visit"
```

---

## Task 2: Backend - Write failing test for AUTO→MANUAL conversion

**Files:**
- Modify: `backend/tests/Functional/Controller/Api/Internal/V1/ShoppingListControllerTest.php`

**Step 1: Add test for AUTO→MANUAL conversion on amount change**

Add this test method to `ShoppingListControllerTest`:

```php
public function testUpdateAmountConvertsAutoToManual(): void
{
    $category = $this->createCategory(['name' => 'Test Category']);
    $location = $this->createLocation(['name' => 'Kitchen']);
    $product = $this->createProduct([
        'name' => 'Auto Product',
        'category' => $category,
        'defaultLocation' => $location,
    ]);

    // Create AUTO item
    $item = $this->createItem([
        'product' => $product,
        'amount' => 5,
        'source' => ShoppingListSource::AUTO,
    ]);

    // Update amount
    $response = $this->apiPatch('/shopping-list/' . $item->getId(), [
        'amount' => 3,
    ]);
    $data = static::assertJsonResponse($response, Response::HTTP_OK);

    // Should be converted to MANUAL
    static::assertSame(3, $data['data']['amount']);
    static::assertSame('manual', $data['data']['source']);
}

public function testUpdateNoteDoesNotConvertAutoToManual(): void
{
    $category = $this->createCategory(['name' => 'Test Category']);
    $location = $this->createLocation(['name' => 'Kitchen']);
    $product = $this->createProduct([
        'name' => 'Auto Product',
        'category' => $category,
        'defaultLocation' => $location,
    ]);

    // Create AUTO item
    $item = $this->createItem([
        'product' => $product,
        'amount' => 5,
        'source' => ShoppingListSource::AUTO,
    ]);

    // Update only note
    $response = $this->apiPatch('/shopping-list/' . $item->getId(), [
        'note' => 'Buy the organic one',
    ]);
    $data = static::assertJsonResponse($response, Response::HTTP_OK);

    // Should remain AUTO
    static::assertSame(5, $data['data']['amount']);
    static::assertSame('auto', $data['data']['source']);
}
```

**Step 2: Run test to verify it fails**

Run: `cd /home/pavel/projects/hestia/backend && make test FILTER=testUpdateAmountConvertsAutoToManual`
Expected: FAIL - source will be 'auto' instead of 'manual'

---

## Task 3: Backend - Implement AUTO→MANUAL conversion

**Files:**
- Modify: `backend/src/Service/ShoppingListService.php:154-176`

**Step 1: Update the updateItem method**

Replace the `updateItem` method:

```php
/**
 * Update an existing shopping list item.
 * Converting AUTO to MANUAL if amount is changed.
 */
public function updateItem(Uuid $id, UpdateShoppingItemRequest $request): ShoppingListItem
{
    $item = $this->shoppingListItemRepository->find($id);
    if ($item === null) {
        throw new ShoppingListItemNotFoundException($id);
    }

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

    if ($request->note !== null) {
        $item->setNote($request->note);
    }

    if ($request->done !== null) {
        $item->setDone($request->done);
    }

    $this->entityManager->flush();

    return $item;
}
```

**Step 2: Run backend lint**

Run: `cd /home/pavel/projects/hestia/backend && mago format && mago lint`
Expected: PASS

**Step 3: Run test to verify it passes**

Run: `cd /home/pavel/projects/hestia/backend && make test FILTER=testUpdateAmountConvertsAutoToManual`
Expected: PASS

Run: `cd /home/pavel/projects/hestia/backend && make test FILTER=testUpdateNoteDoesNotConvertAutoToManual`
Expected: PASS

**Step 4: Run full test suite**

Run: `cd /home/pavel/projects/hestia/backend && make test`
Expected: All tests pass

**Step 5: Commit**

```bash
git add backend/src/Service/ShoppingListService.php backend/tests/Functional/Controller/Api/Internal/V1/ShoppingListControllerTest.php
git commit -s -m "fix(backend): convert AUTO to MANUAL when amount is changed"
```

---

## Task 4: Backend - Update test for current deficit tracking

**Files:**
- Modify: `backend/tests/Functional/Controller/Api/Internal/V1/ShoppingListAutoAddTest.php`

**Step 1: Replace testAutoAddNeverDecreasesAmount with testAutoAddTracksCurrentDeficit**

Find and replace the test method (around line 122):

```php
public function testAutoAddTracksCurrentDeficit(): void
{
    $category = $this->createCategory(['name' => 'Test Category']);
    $location = $this->createLocation(['name' => 'Kitchen']);
    $product = $this->createProduct([
        'name' => 'Test Product',
        'category' => $category,
        'defaultLocation' => $location,
        'minStock' => 10
    ]);

    // First change: stock is 2, deficit is 8
    $this->shoppingListService->handleStockChange($product->getId(), 10, 2);

    $response = $this->apiGet('/shopping-list');
    $data = static::assertJsonResponse($response, Response::HTTP_OK);
    static::assertSame(8, $data['data'][0]['amount']);

    // Second change: stock is 5, deficit is 5 (user bought 3)
    $this->shoppingListService->handleStockChange($product->getId(), 2, 5);

    $response = $this->apiGet('/shopping-list');
    $data = static::assertJsonResponse($response, Response::HTTP_OK);
    // Now tracks current deficit, not historical max
    static::assertSame(5, $data['data'][0]['amount']);
}
```

**Step 2: Run test to verify it fails**

Run: `cd /home/pavel/projects/hestia/backend && make test FILTER=testAutoAddTracksCurrentDeficit`
Expected: FAIL - amount will be 8 instead of 5

---

## Task 5: Backend - Implement current deficit tracking

**Files:**
- Modify: `backend/src/Service/ShoppingListService.php:62-90`

**Step 1: Update the upsertAutoItem method**

Replace the `upsertAutoItem` method:

```php
/**
 * Add or update an auto-generated shopping list item.
 * Amount tracks current deficit (updates both up and down).
 */
private function upsertAutoItem(Product $product, int $deficit): void
{
    $existing = $this->shoppingListItemRepository->findByProduct($product);

    if ($existing !== null) {
        // If it's a manual item, don't touch it
        if ($existing->getSource() !== ShoppingListSource::AUTO) {
            return;
        }

        // Update amount to current deficit
        if ($deficit !== $existing->getAmount()) {
            $existing->setAmount($deficit);
            $this->entityManager->flush();
        }

        return;
    }

    // Create new auto item
    $item = new ShoppingListItem();
    $item->setProduct($product);
    $item->setAmount($deficit);
    $item->setSource(ShoppingListSource::AUTO);

    $this->entityManager->persist($item);
    $this->entityManager->flush();
}
```

**Step 2: Update related tests that expected "never decrease" behavior**

In `ShoppingListAutoAddTest.php`, update `testAutoAddWhenDeficitEqualsCurrent` (around line 352):

```php
public function testAutoAddWhenDeficitEqualsCurrent(): void
{
    $category = $this->createCategory(['name' => 'Test Category']);
    $location = $this->createLocation(['name' => 'Kitchen']);
    $product = $this->createProduct([
        'name' => 'Test Product',
        'category' => $category,
        'defaultLocation' => $location,
        'minStock' => 10
    ]);

    // Create auto item with amount 5
    $this->createItem([
        'product' => $product,
        'amount' => 5,
        'source' => ShoppingListSource::AUTO
    ]);

    // Stock change results in deficit of exactly 5 (same as current)
    $this->shoppingListService->handleStockChange($product->getId(), 3, 5);

    // Amount should stay at 5 (no change needed)
    $response = $this->apiGet('/shopping-list');
    $data = static::assertJsonResponse($response, Response::HTTP_OK);
    static::assertSame(5, $data['data'][0]['amount']);
}
```

Update `testAutoAddDoesNotUpdateWhenDeficitEqualsExisting` (around line 558):

```php
public function testAutoAddUpdatesToCurrentDeficit(): void
{
    $category = $this->createCategory(['name' => 'Test Category']);
    $location = $this->createLocation(['name' => 'Kitchen']);
    $product = $this->createProduct([
        'name' => 'Test Product',
        'category' => $category,
        'defaultLocation' => $location,
        'minStock' => 10
    ]);

    // Create auto item with amount 5
    $item = $this->createItem([
        'product' => $product,
        'amount' => 5,
        'source' => ShoppingListSource::AUTO
    ]);
    $itemId = $item->getId();

    // Stock is 7, minStock is 10 - deficit is 3 (less than current 5)
    $this->shoppingListService->handleStockChange($product->getId(), 5, 7);

    // Amount should update to 3 (current deficit)
    $this->assertDatabaseHas(ShoppingListItem::class, [
        'id' => $itemId,
        'amount' => 3
    ]);
}
```

**Step 3: Run backend lint**

Run: `cd /home/pavel/projects/hestia/backend && mago format && mago lint`
Expected: PASS

**Step 4: Run tests to verify they pass**

Run: `cd /home/pavel/projects/hestia/backend && make test FILTER=testAutoAddTracksCurrentDeficit`
Expected: PASS

Run: `cd /home/pavel/projects/hestia/backend && make test FILTER=testAutoAddUpdatesToCurrentDeficit`
Expected: PASS

**Step 5: Run full test suite**

Run: `cd /home/pavel/projects/hestia/backend && make test`
Expected: All tests pass

**Step 6: Commit**

```bash
git add backend/src/Service/ShoppingListService.php backend/tests/Functional/Controller/Api/Internal/V1/ShoppingListAutoAddTest.php
git commit -s -m "fix(backend): track current deficit instead of historical max"
```

---

## Task 6: Final verification

**Step 1: Run all backend tests**

Run: `cd /home/pavel/projects/hestia/backend && make test`
Expected: All tests pass

**Step 2: Run all frontend tests**

Run: `cd /home/pavel/projects/hestia/frontend && bun run test:run`
Expected: All tests pass

**Step 3: Run frontend check**

Run: `cd /home/pavel/projects/hestia/frontend && bun run check`
Expected: PASS

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Refresh on page visit | `ShoppingPage.tsx` |
| 2 | Test for AUTO→MANUAL | `ShoppingListControllerTest.php` |
| 3 | Implement AUTO→MANUAL | `ShoppingListService.php` |
| 4 | Test for current deficit | `ShoppingListAutoAddTest.php` |
| 5 | Implement current deficit | `ShoppingListService.php` |
| 6 | Final verification | - |
