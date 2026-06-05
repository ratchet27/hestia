# C2 — Sync min-stock reconciliation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the min-stock → shopping-list reconciliation run synchronously in-request and self-correct by re-querying current stock, so it never depends on a RabbitMQ worker and is idempotent.

**Architecture:** Keep the existing event seam (`StockChangedMessage` → `StockChangedHandler` → `ShoppingListService::handleStockChange`) but unroute the message so Messenger runs the handler synchronously in-process. The handler re-queries `countByProduct` for ground truth instead of trusting a quantity snapshot, and swallows+logs reconciliation failures so the user's committed stock op never fails. The product-edit path converges onto the same dispatch.

**Tech Stack:** PHP 8.4, Symfony 7 (Messenger, Validator), Doctrine ORM, PostgreSQL, PHPUnit + Zenstruck Foundry, run inside Docker (`docker compose exec php`), gate via `make lint` + `make test`.

**Spec:** `docs/superpowers/specs/2026-06-06-c2-stock-reconciliation-sync-design.md`

---

## Conventions for every task

- All PHP/composer/phpunit commands run **inside Docker**: prefix with `docker compose exec -T php`.
- Backend gate is **`make lint`** (runs rector → mago format → mago lint → mago analyze → phpstan) — never a subset.
- `make lint` rewrites files; **stage explicitly** (`git add <paths>`), never `git add -A`.
- Commits: `git commit -s -m "<type>(<scope>): <desc>"` (signoff required; GPG auto-configured). GitHub account: `ratchet27`.
- Run a single test class: `docker compose exec -T php bin/phpunit --filter ShoppingListAutoAddTest`.

---

## File map

| File | Change |
|------|--------|
| `config/packages/messenger.yaml` | Remove `StockChangedMessage: async` routing line (Task 1) |
| `tests/.../ShoppingListAutoAddTest.php` | Convert 5 transport-assertion tests (T1); add `setStockLevel` helper + rewrite 21 direct-call tests (T2); add idempotency + min-stock-edit tests (T2/T3) |
| `src/Service/ShoppingListService.php` | Inject `StockEntryRepository`; `handleStockChange(Uuid)` re-queries (T2) |
| `src/MessageHandler/StockChangedHandler.php` | Call 1-arg handler (T2); add try/catch + logger (T4) |
| `src/Message/StockChangedMessage.php` | Reduce to `productId` only (T3) |
| `src/Service/StockEntryService.php` | Dispatch `new StockChangedMessage($productId)` at 4 sites; drop message-only qty math (T3) |
| `src/Service/ProductService.php` | Dispatch instead of direct call; swap `ShoppingListService`+`StockEntryRepository` deps for `MessageBusInterface` (T3) |
| `tests/Unit/MessageHandler/StockChangedHandlerTest.php` | New unit test for swallow+log (T4) |

---

## Task 1: Make reconciliation synchronous (unroute the message)

After this task, a stock change reconciles the shopping list **within the same request**, with no worker. Production change is one line in `messenger.yaml`; the rest is test conversion. The handler still uses the 3-arg snapshot signature here — that's slimmed in Task 2.

**Files:**
- Modify: `config/packages/messenger.yaml`
- Test: `tests/Functional/Controller/Api/Internal/V1/ShoppingListAutoAddTest.php`

- [ ] **Step 1: Write the failing headline test**

Add this method to `ShoppingListAutoAddTest` (after the `// ========== Integration with Stock API ==========` marker):

```php
public function testConsumeBelowMinReconcilesInRequestWithoutWorker(): void
{
    $location = $this->createLocation(['name' => 'Kitchen']);
    $product = $this->createProduct(['name' => 'Milk', 'defaultLocation' => $location, 'minStock' => 5]);

    for ($i = 0; $i < 6; $i++) {
        StockEntryFactory::createOne(['product' => $product, 'location' => $location]);
    }

    // Consume 3 (6 -> 3, below min of 5). No messenger:consume is run by this test.
    $response = $this->apiPost('/stocks/consume', [
        'product_id' => (string) $product->getId(),
        'location_id' => (string) $location->getId(),
        'quantity' => 3,
    ]);
    static::assertJsonResponse($response, Response::HTTP_OK);

    // Reconciliation already happened in-request — the auto item exists now.
    $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
    static::assertListResponse($data, 1);
    static::assertSame('auto', $data['data'][0]['source']);
    static::assertSame(2, $data['data'][0]['amount']); // deficit 5 - 3
}
```

- [ ] **Step 2: Run it and verify it fails**

Run: `docker compose exec -T php bin/phpunit --filter testConsumeBelowMinReconcilesInRequestWithoutWorker`
Expected: FAIL — list is empty (`assertListResponse($data, 1)` fails) because the message sits unprocessed on the async in-memory transport.

- [ ] **Step 3: Unroute `StockChangedMessage`**

In `config/packages/messenger.yaml`, delete the StockChangedMessage routing line so only the daily summary stays async:

```yaml
        routing:
            'App\Message\SendDailyExpirySummary': async
```

(Leave the `transports` block and the `when@test` override untouched. An unrouted message with a handler is dispatched synchronously in-process.)

- [ ] **Step 4: Run the new test and verify it passes**

Run: `docker compose exec -T php bin/phpunit --filter testConsumeBelowMinReconcilesInRequestWithoutWorker`
Expected: PASS.

- [ ] **Step 5: Convert the 5 transport-assertion tests to in-request assertions**

These tests pull from `messenger.transport.async`, which is now empty — they will fail. Replace each as below. Also remove the now-unused imports at the top of the file: `use Symfony\Component\Messenger\Envelope;` and `use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;` (keep `StockChangedMessage` import — still referenced until Task 3).

Replace `testConsumeStockDispatchesMessage` body's transport block (the `/** @var InMemoryTransport ... */` … `assertGreaterThanOrEqual` lines) with:

```php
    $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
    static::assertListResponse($data, 1);
    static::assertSame('auto', $data['data'][0]['source']);
    static::assertSame(2, $data['data'][0]['amount']); // min 5 - remaining 3
```

Replace `testAddStockDispatchesMessage` transport block (product min 5, added 3 → still below min, deficit 2) with:

```php
    $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
    static::assertListResponse($data, 1);
    static::assertSame('auto', $data['data'][0]['source']);
    static::assertSame(2, $data['data'][0]['amount']); // min 5 - stock 3
```

Replace `testAddStockDispatchesMessageWithCorrectQuantities` (3 existing + add 2 = 5 = min → no deficit, no item). Update the docblock to `Verifies add reconciles to the real stock level in-request (no deficit at min).` and replace the transport block with:

```php
    $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
    static::assertListResponse($data, 0); // 5 == min, nothing to buy
```

Replace `testDeleteEntryDispatchesMessageWithCorrectQuantities` (5 entries, min 5, delete 1 → 4, deficit 1). Update the docblock to `Verifies delete reconciles to the real stock level in-request.` and replace the transport block with:

```php
    $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
    static::assertListResponse($data, 1);
    static::assertSame('auto', $data['data'][0]['source']);
    static::assertSame(1, $data['data'][0]['amount']); // min 5 - stock 4
```

Replace `testConsumeStockDispatchesExactlyOneMessage` (5 entries, min 5, consume 2 → 3, deficit 2). Rename to `testConsumeStockReconcilesOnceInRequest`, update the docblock to `Consuming below min yields exactly one auto item (no duplicates).`, and replace the transport block with:

```php
    $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
    static::assertListResponse($data, 1); // exactly one item, not duplicated
    static::assertSame('auto', $data['data'][0]['source']);
    static::assertSame(2, $data['data'][0]['amount']);
```

- [ ] **Step 6: Run the full test class and verify green**

Run: `docker compose exec -T php bin/phpunit --filter ShoppingListAutoAddTest`
Expected: the 6 touched tests PASS. The ~21 direct-call tests still pass too (handler still 3-arg snapshot; not yet re-querying).

- [ ] **Step 7: Lint and commit**

```bash
docker compose exec -T php make lint   # if make runs on host instead, use: cd backend && make lint
git add config/packages/messenger.yaml tests/Functional/Controller/Api/Internal/V1/ShoppingListAutoAddTest.php
git commit -s -m "fix(stock): handle StockChangedMessage synchronously in-request (#54)"
```

> Note: per CLAUDE.md, `make lint` runs locally on the host (`cd /home/pavel/projects/personal/hestia/backend && make lint`); `mago` must NOT run inside Docker. PHP/phpunit run via `docker compose exec`.

---

## Task 2: Make the handler self-correcting (re-query current stock)

`handleStockChange` stops trusting a quantity argument and re-queries `countByProduct`. The signature drops to `(Uuid $productId)`. This makes it idempotent and order-independent. All direct-call tests must seed **real** stock rows matching the intended level.

**Files:**
- Modify: `src/Service/ShoppingListService.php`
- Modify: `src/MessageHandler/StockChangedHandler.php:18-25` (the `__invoke` call only)
- Modify: `src/Service/ProductService.php:145-147` (call site — temporary 1-arg call; fully converged in Task 3)
- Test: `tests/Functional/Controller/Api/Internal/V1/ShoppingListAutoAddTest.php`

- [ ] **Step 1: Write a failing idempotency test**

Add to `ShoppingListAutoAddTest`:

```php
public function testHandleStockChangeIsIdempotentAcrossRepeatedCalls(): void
{
    $location = $this->createLocation(['name' => 'Kitchen']);
    $product = $this->createProduct(['name' => 'Eggs', 'defaultLocation' => $location, 'minStock' => 5]);
    $this->setStockLevel($product, $location, 2); // deficit 3

    $this->shoppingListService->handleStockChange($product->getId());
    $this->shoppingListService->handleStockChange($product->getId());
    $this->shoppingListService->handleStockChange($product->getId());

    $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
    static::assertListResponse($data, 1); // exactly one auto item despite repeated calls
    static::assertSame(3, $data['data'][0]['amount']);
}
```

- [ ] **Step 2: Run it and verify it fails to compile/run**

Run: `docker compose exec -T php bin/phpunit --filter testHandleStockChangeIsIdempotentAcrossRepeatedCalls`
Expected: FAIL — `setStockLevel` is undefined and `handleStockChange` requires 3 args (`ArgumentCountError`).

- [ ] **Step 3: Add the `setStockLevel` test helper**

Add these imports to the test file if missing: `use App\Repository\StockEntryRepository;` and `use Doctrine\ORM\EntityManagerInterface;`. Add this private method to the class (near the other `create*` helpers):

```php
private function setStockLevel(Product $product, Location $location, int $target): void
{
    /** @var StockEntryRepository $repo */
    $repo = static::getContainer()->get(StockEntryRepository::class);
    $current = $repo->countByProduct($product->getId());

    if ($target > $current) {
        StockEntryFactory::createMany($target - $current, [
            'product' => $product,
            'location' => $location,
        ]);

        return;
    }

    if ($target < $current) {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $entries = $repo->findBy(['product' => $product]);
        foreach (array_slice($entries, 0, $current - $target) as $entry) {
            $em->remove($entry);
        }
        $em->flush();
    }
}
```

- [ ] **Step 4: Re-query in `ShoppingListService::handleStockChange`**

Inject the repository and rewrite the method. Add `use App\Repository\StockEntryRepository;` (already imports `ProductRepository`). Add the constructor param:

```php
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ShoppingListItemRepository $shoppingListItemRepository,
        private ProductRepository $productRepository,
        private StockEntryRepository $stockEntryRepository
    ) {
    }
```

Replace the whole `handleStockChange` method (currently lines ~27-57, the docblock + 3-arg body) with:

```php
    /**
     * Reconcile the shopping list against a product's current stock level.
     *
     * Re-queries the live stock count (does not trust any caller-supplied quantity),
     * so it is idempotent and order-independent.
     */
    public function handleStockChange(Uuid $productId): void
    {
        $product = $this->productRepository->find($productId);
        if ($product === null || !$product->isActive()) {
            return;
        }

        $currentStock = $this->stockEntryRepository->countByProduct($productId);
        // When minStock=0, deficit is 0, which removes any stale auto item below.
        $deficit = max(0, $product->getMinStock() - $currentStock);

        if ($deficit > 0) {
            $this->upsertAutoItem($product, $deficit);

            return;
        }

        $this->removeAutoItem($product);
    }
```

Note the removed `_previousQty` param/comment and the dropped `@infection-ignore-all: Equivalent mutant` line (it described the `max(-1, x)` snapshot edge — no longer relevant). Leave `upsertAutoItem`/`removeAutoItem` unchanged.

- [ ] **Step 5: Update the handler call to 1-arg**

In `src/MessageHandler/StockChangedHandler.php`, change `__invoke`:

```php
    public function __invoke(StockChangedMessage $message): void
    {
        $this->shoppingListService->handleStockChange($message->productId);
    }
```

- [ ] **Step 6: Update the ProductService direct call to 1-arg (temporary)**

In `src/Service/ProductService.php`, change the min-stock recompute block to:

```php
        // Recalculate shopping list if minStock changed
        if ($request->minStock !== $oldMinStock) {
            $this->shoppingListService->handleStockChange($id);
        }
```

(The `$currentStock = $this->stockEntryRepository->countByProduct($id);` line is deleted. The repository dep is removed in Task 3 once nothing else uses it.)

- [ ] **Step 7: Rewrite the direct-call tests to seed real stock (22 calls across ~19 tests)**

Each existing call `handleStockChange($id, _prev, N)` becomes:

```php
$this->setStockLevel($product, $location, N);
$this->shoppingListService->handleStockChange($product->getId());
```

The **3rd original argument is the target stock level `N`**. Tests with two sequential calls get two `setStockLevel` + call pairs in order. Tests that pre-create a manual/recipe/auto `ShoppingListItem` keep that setup unchanged. Worked example — `testAutoAddIncreasesAmountWhenDeficitGrows` (min 10) becomes:

```php
    $this->setStockLevel($product, $location, 8); // deficit 2
    $this->shoppingListService->handleStockChange($product->getId());
    $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
    static::assertSame(2, $data['data'][0]['amount']);

    $this->setStockLevel($product, $location, 3); // deficit 7
    $this->shoppingListService->handleStockChange($product->getId());
    $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
    static::assertSame(7, $data['data'][0]['amount']);
```

Apply to every direct call. Targets per call (line in current file → target `N`):

| Line | Test | Target(s) N |
|------|------|-------------|
| 86 | testAutoAddWhenStockBelowMinimum | 2 |
| 110, 117 | testAutoAddIncreasesAmountWhenDeficitGrows | 8, then 3 |
| 136, 143 | testAutoAddTracksCurrentDeficit | 2, then 5 |
| 170 | testAutoRemoveWhenStockReachesMinimum | 5 |
| 197 | testAutoRemoveWhenStockExceedsMinimum | 10 |
| 224 | testManualItemNotRemovedWhenStockReachesMinimum | 5 |
| 252 | testRecipeItemNotRemovedWhenStockReachesMinimum | 5 |
| 273 | testNoActionForProductWithoutMinStock | 0 |
| 294 | testNoActionForInactiveProduct | 0 (guard returns before count) |
| 307 | testNoActionForNonExistentProduct | no seeding — just `handleStockChange($fakeId)` |
| 326 | testAutoAddWithExactlyZeroDeficit | 5 |
| 346 | testAutoAddWithDeficitOfOne | 4 |
| 373 | testAutoAddWhenDeficitEqualsCurrent | 5 |
| 400 | testManualItemNotOverwrittenByAutoAdd | 2 |
| 488 | testMinStockZeroNeverCreatesItem | 0 |
| 526 | testNoAutoAddWhenStockExceedsMinStock | 10 |
| 548 | testAutoAddUpdatesToCurrentDeficit | 10 |
| 576 | testUpsertAutoItemDoesNotDuplicateOnNoUpdate | 7 |
| 608 | testUpsertAutoItemDoesNotDuplicateOnNoUpdate (2nd call) | 7 |
| 789 | testMinStockDecreaseRemovesAutoItem | 3 |

For `testNoActionForInactiveProduct`, the product is inactive so the early guard returns before counting; `setStockLevel(..., 0)` is a harmless no-op — call `handleStockChange($product->getId())` after it.

- [ ] **Step 8: Run the full class and verify green**

Run: `docker compose exec -T php bin/phpunit --filter ShoppingListAutoAddTest`
Expected: PASS (all direct tests + idempotency + the Task-1 in-request tests).

- [ ] **Step 9: Lint and commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/Service/ShoppingListService.php src/MessageHandler/StockChangedHandler.php src/Service/ProductService.php tests/Functional/Controller/Api/Internal/V1/ShoppingListAutoAddTest.php
git commit -s -m "fix(stock): reconcile shopping list from live stock count, not snapshot (#54)"
```

---

## Task 3: Slim the message to `productId` and converge the product-edit path

Remove the now-dead quantity fields from `StockChangedMessage`, update the 4 dispatch sites, and route product min-stock edits through the same dispatch (single mechanism).

**Files:**
- Modify: `src/Message/StockChangedMessage.php`
- Modify: `src/Service/StockEntryService.php` (dispatch sites ~:98, :143, :172, :224 + their qty math)
- Modify: `src/Service/ProductService.php` (deps + dispatch)
- Test: `tests/Functional/Controller/Api/Internal/V1/ShoppingListAutoAddTest.php`

- [ ] **Step 1: Write a failing min-stock-edit convergence test**

Add to `ShoppingListAutoAddTest`:

```php
public function testRaisingMinStockViaApiAddsAutoItemInRequest(): void
{
    $location = $this->createLocation(['name' => 'Kitchen']);
    $category = $this->createCategory(['name' => 'Dairy']);
    $product = $this->createProduct([
        'name' => 'Butter', 'category' => $category, 'defaultLocation' => $location, 'minStock' => 1,
    ]);
    $this->setStockLevel($product, $location, 2); // at min 1, no deficit

    // Raise min to 5 -> deficit 3, must reconcile in-request via the same mechanism.
    $response = $this->apiPut('/products/' . $product->getId(), [
        'name' => 'Butter',
        'category_id' => (string) $category->getId(),
        'minStock' => 5,
        'active' => true,
    ]);
    static::assertJsonResponse($response, Response::HTTP_OK);

    $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
    static::assertListResponse($data, 1);
    static::assertSame('auto', $data['data'][0]['source']);
    static::assertSame(3, $data['data'][0]['amount']);
}
```

> Before running, confirm the product update route/verb and required payload fields against `ProductController` (the path may be `/products/{id}` with PUT; adjust the call and required fields — e.g. `defaultLocationId`, `unit` — to match the existing `testMinStockIncreaseCreatesAutoItem` test in this file, which already exercises the update endpoint).

- [ ] **Step 2: Run it and verify it passes already (or fails only on payload)**

Run: `docker compose exec -T php bin/phpunit --filter testRaisingMinStockViaApiAddsAutoItemInRequest`
Expected: PASS once the payload matches (ProductService already calls `handleStockChange($id)` after Task 2). This test locks the behavior in before the dependency swap so the refactor below is safe.

- [ ] **Step 3: Slim `StockChangedMessage`**

Replace the whole class body of `src/Message/StockChangedMessage.php`:

```php
/**
 * Dispatched after a product's stock level changes, to reconcile the shopping list.
 * Carries only the product id; the handler re-queries the live stock count.
 */
final readonly class StockChangedMessage
{
    public function __construct(
        public Uuid $productId
    ) {
    }
}
```

- [ ] **Step 4: Update the 4 dispatch sites in `StockEntryService`**

At each site (`addStock` ~:98, `consumeStock` ~:143, `consumeAcrossLocations` ~:172, `deleteEntry` ~:224), change the dispatch to:

```php
$this->messageBus->dispatch(new StockChangedMessage($productId));
```

Delete the now-unused `$newQty` computations that **only** fed the message:
- `addStock`: remove `$newQty = $previousQty + $request->quantity;` and the now-unused `$previousQty = $this->stockEntryRepository->countByProduct($productId);` (verify `$previousQty` isn't used elsewhere in the method — it isn't).
- `consumeStock`: remove `$newQty = $this->stockEntryRepository->countByProduct($productId);` and the earlier `$previousQty = ...` **only if** unused otherwise. Keep `$remaining = countByProductAndLocation(...)` — it feeds `ConsumeResultResponse.remaining_at_location`.
- `consumeAcrossLocations`: keep `$previousQty` (used for the `InsufficientStockException` guard); remove `$newQty = $previousQty - $quantity;`.
- `deleteEntry`: remove `$previousQty = ...` and `$newQty = $previousQty - 1;` (neither used after the message slims).

- [ ] **Step 5: Converge `ProductService` onto dispatch**

In `src/Service/ProductService.php`:
- Add `use App\Message\StockChangedMessage;` and `use Symfony\Component\Messenger\MessageBusInterface;`.
- In the constructor, **remove** `private readonly StockEntryRepository $stockEntryRepository,` and `private readonly ShoppingListService $shoppingListService,` (confirm via grep they are unused elsewhere — they are only at :146-147). Remove their now-unused `use` imports (`App\Repository\StockEntryRepository`; `ShoppingListService` has no import — it's same namespace). **Add** `private readonly MessageBusInterface $messageBus,`.
- Replace the recompute block:

```php
        // Reconcile shopping list if minStock changed (same mechanism as stock changes)
        if ($request->minStock !== $oldMinStock) {
            $this->messageBus->dispatch(new StockChangedMessage($id));
        }
```

- [ ] **Step 6: Remove the obsolete `StockChangedMessage` import from the test if now unused**

Grep the test file for `StockChangedMessage`; if no references remain after Task 1's conversions, remove `use App\Message\StockChangedMessage;`.

- [ ] **Step 7: Run the full class + verify no other callers broke**

```bash
docker compose exec -T php bin/phpunit --filter ShoppingListAutoAddTest
docker compose exec -T php bin/phpunit --filter ProductControllerTest
docker compose exec -T php bin/phpunit --filter StockControllerTest
```
Expected: PASS.

- [ ] **Step 8: Lint and commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/Message/StockChangedMessage.php src/Service/StockEntryService.php src/Service/ProductService.php tests/Functional/Controller/Api/Internal/V1/ShoppingListAutoAddTest.php
git commit -s -m "refactor(stock): carry only productId; converge product-edit onto dispatch (#54)"
```

---

## Task 4: Never fail the user's stock op on a reconciliation hiccup

The handler now runs in the user's request after the stock change has committed. Wrap reconciliation in try/catch + log so a failure is observable but non-fatal.

**Files:**
- Modify: `src/MessageHandler/StockChangedHandler.php`
- Test: `tests/Unit/MessageHandler/StockChangedHandlerTest.php` (new)

- [ ] **Step 1: Write the failing unit test**

Create `tests/Unit/MessageHandler/StockChangedHandlerTest.php` (mirrors `SendDailyExpirySummaryHandlerTest`):

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\StockChangedMessage;
use App\MessageHandler\StockChangedHandler;
use App\Service\ShoppingListService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final class StockChangedHandlerTest extends TestCase
{
    public function testReconciliationFailureIsLoggedAndSwallowed(): void
    {
        $service = $this->createMock(ShoppingListService::class);
        $service->method('handleStockChange')->willThrowException(new \RuntimeException('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning');

        $handler = new StockChangedHandler($service, $logger);

        // Must not throw — the committed stock op stays successful.
        $handler(new StockChangedMessage(Uuid::v4()));
    }

    public function testReconciliationIsDelegatedWithProductId(): void
    {
        $productId = Uuid::v4();
        $service = $this->createMock(ShoppingListService::class);
        $service->expects(static::once())->method('handleStockChange')->with($productId);

        $handler = new StockChangedHandler($service, $this->createMock(LoggerInterface::class));
        $handler(new StockChangedMessage($productId));
    }
}
```

- [ ] **Step 2: Run it and verify it fails**

Run: `docker compose exec -T php bin/phpunit --filter StockChangedHandlerTest`
Expected: FAIL — `testReconciliationFailureIsLoggedAndSwallowed` errors because the exception propagates and the constructor has no `LoggerInterface` arg.

- [ ] **Step 3: Add try/catch + logger to the handler**

Replace `src/MessageHandler/StockChangedHandler.php` with:

```php
<?php

declare(strict_types = 1);

namespace App\MessageHandler;

use App\Message\StockChangedMessage;
use App\Service\ShoppingListService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StockChangedHandler
{
    public function __construct(
        private ShoppingListService $shoppingListService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(StockChangedMessage $message): void
    {
        try {
            $this->shoppingListService->handleStockChange($message->productId);
        } catch (\Throwable $e) {
            // Stock change is already committed; a reconciliation hiccup must not fail
            // the user's operation. It self-corrects on the next stock change.
            $this->logger->warning('Shopping-list reconciliation failed', [
                'productId' => (string) $message->productId,
                'exception' => $e,
            ]);
        }
    }
}
```

- [ ] **Step 4: Run the unit test and verify it passes**

Run: `docker compose exec -T php bin/phpunit --filter StockChangedHandlerTest`
Expected: PASS (both methods).

- [ ] **Step 5: Lint and commit**

```bash
cd /home/pavel/projects/personal/hestia/backend && make lint
git add src/MessageHandler/StockChangedHandler.php tests/Unit/MessageHandler/StockChangedHandlerTest.php
git commit -s -m "fix(stock): log and swallow reconciliation failures so stock ops never 500 (#54)"
```

---

## Final verification

- [ ] **Full gate**

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint && make test
grep -n "StockChangedMessage\|SendDailyExpirySummary" config/packages/messenger.yaml  # only SendDailyExpirySummary -> async
```
Expected: `make lint` + `make test` green; the grep shows `StockChangedMessage` is no longer routed to async.

- [ ] **Docs reconciliation**

Re-read spec §12 wording and `docs/reviews/2026-06-05-backend-architecture/README.md` § C2. If either still describes the async/snapshot design as current, add a short note that C2 is resolved (sync, re-query). Commit any doc edits with `docs(...)`.

- [ ] **Stale-context sweep (per spec "Hard-evaluate first")**

Confirm no leftover references to `previousQuantity`/`newQuantity`, no stale `_previousQty` comment, and no test named `*DispatchesMessage*` still asserting on a transport. Grep:

```bash
cd /home/pavel/projects/personal/hestia/backend
grep -rn "previousQuantity\|newQuantity\|_previousQty\|messenger.transport.async" src/ tests/
```
Expected: no hits (or only intentional ones you can justify).
