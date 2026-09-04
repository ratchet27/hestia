<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Category;
use App\Entity\Location;
use App\Entity\Product;
use App\Entity\ShoppingListItem;
use App\Enum\ShoppingListSource;
use App\Factory\CategoryFactory;
use App\Factory\LocationFactory;
use App\Factory\ProductFactory;
use App\Factory\ShoppingListItemFactory;
use App\Factory\StockEntryFactory;
use App\Factory\UserFactory;
use App\Repository\StockEntryRepository;
use App\Service\ShoppingListService;
use App\Tests\Functional\Trait\ApiTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Tests for the auto-add/remove shopping list functionality triggered by stock changes.
 */
// @mago-ignore lint:too-many-methods
class ShoppingListAutoAddTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    private ShoppingListService $shoppingListService;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loginAs(UserFactory::createOne());
        $this->shoppingListService = static::getContainer()->get(ShoppingListService::class);
    }

    /** @param array<string, mixed> $attributes */
    private function createProduct(array $attributes = []): Product
    {
        return ProductFactory::createOne($attributes);
    }

    /** @param array<string, mixed> $attributes */
    private function createCategory(array $attributes = []): Category
    {
        return CategoryFactory::createOne($attributes);
    }

    /** @param array<string, mixed> $attributes */
    private function createLocation(array $attributes = []): Location
    {
        return LocationFactory::createOne($attributes);
    }

    /** @param array<string, mixed> $attributes */
    private function createItem(array $attributes = []): ShoppingListItem
    {
        return ShoppingListItemFactory::createOne($attributes);
    }

    /**
     * Drive a product's stock to an absolute count.
     * $location is used only when creating entries (increase path); the decrease
     * path removes entries across all locations for the product.
     */
    private function setStockLevel(Product $product, Location $location, int $target): void
    {
        /** @var StockEntryRepository $repo */
        $repo = static::getContainer()->get(StockEntryRepository::class);
        $current = $repo->countByProduct($product->getId());

        if ($target > $current) {
            StockEntryFactory::createMany($target - $current, [
                'product' => $product,
                'location' => $location
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

    // ========== handleStockChange Direct Tests ==========

    public function testAutoAddWhenStockBelowMinimum(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Simulate stock level: 2 entries (deficit 5 - 2 = 3)
        $this->setStockLevel($product, $location, 2);
        $this->shoppingListService->handleStockChange($product->getId());

        // Should create auto item with deficit amount (5 - 2 = 3)
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame('Test Product', $data['data'][0]['name']);
        static::assertSame(3, $data['data'][0]['amount']);
        static::assertSame('auto', $data['data'][0]['source']);
    }

    public function testAutoAddIncreasesAmountWhenDeficitGrows(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 10
        ]);

        // First: stock is 8, deficit is 2
        $this->setStockLevel($product, $location, 8);
        $this->shoppingListService->handleStockChange($product->getId());

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertSame(2, $data['data'][0]['amount']);

        // Second: stock is 3, deficit is 7
        $this->setStockLevel($product, $location, 3);
        $this->shoppingListService->handleStockChange($product->getId());

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertSame(7, $data['data'][0]['amount']);
    }

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

        // First: stock is 2, deficit is 8
        $this->setStockLevel($product, $location, 2);
        $this->shoppingListService->handleStockChange($product->getId());

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertSame(8, $data['data'][0]['amount']);

        // Second: stock is 5, deficit is 5 (user bought 3)
        $this->setStockLevel($product, $location, 5);
        $this->shoppingListService->handleStockChange($product->getId());

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        // Now tracks current deficit, not historical max
        static::assertSame(5, $data['data'][0]['amount']);
    }

    public function testAutoRemoveWhenStockReachesMinimum(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Create auto item
        $this->createItem([
            'product' => $product,
            'amount' => 3,
            'source' => ShoppingListSource::AUTO
        ]);

        // Stock reaches minimum
        $this->setStockLevel($product, $location, 5);
        $this->shoppingListService->handleStockChange($product->getId());

        // Auto item should be removed
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 0);
    }

    public function testAutoRemoveWhenStockExceedsMinimum(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Create auto item
        $this->createItem([
            'product' => $product,
            'amount' => 3,
            'source' => ShoppingListSource::AUTO
        ]);

        // Stock exceeds minimum
        $this->setStockLevel($product, $location, 10);
        $this->shoppingListService->handleStockChange($product->getId());

        // Auto item should be removed
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 0);
    }

    public function testManualItemNotRemovedWhenStockReachesMinimum(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Create manual item
        $this->createItem([
            'product' => $product,
            'amount' => 3,
            'source' => ShoppingListSource::MANUAL
        ]);

        // Stock reaches minimum
        $this->setStockLevel($product, $location, 5);
        $this->shoppingListService->handleStockChange($product->getId());

        // Manual item should NOT be removed
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame('manual', $data['data'][0]['source']);
    }

    public function testRecipeItemNotRemovedWhenStockReachesMinimum(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Create recipe item
        $this->createItem([
            'product' => $product,
            'amount' => 3,
            'source' => ShoppingListSource::RECIPE
        ]);

        // Stock reaches minimum
        $this->setStockLevel($product, $location, 5);
        $this->shoppingListService->handleStockChange($product->getId());

        // Recipe item should NOT be removed
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame('recipe', $data['data'][0]['source']);
    }

    public function testNoActionForProductWithoutMinStock(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 0
        ]);

        // Stock change on product with no minStock (setStockLevel with 0 is a no-op)
        $this->setStockLevel($product, $location, 0);
        $this->shoppingListService->handleStockChange($product->getId());

        // No shopping item should be created
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 0);
    }

    public function testInactiveProductRemovesAutoItemButKeepsManualItem(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $archived = $this->createProduct([
            'name' => 'Archived Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5,
            'active' => false
        ]);
        $wanted = $this->createProduct([
            'name' => 'Still Wanted',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5,
            'active' => false
        ]);
        $this->createItem(['product' => $archived, 'amount' => 5, 'source' => ShoppingListSource::AUTO]);
        $this->createItem(['product' => $wanted, 'amount' => 2, 'source' => ShoppingListSource::MANUAL]);

        $this->shoppingListService->handleStockChange($archived->getId());
        $this->shoppingListService->handleStockChange($wanted->getId());

        // The AUTO item for the archived product is gone; the household's MANUAL item survives.
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame('Still Wanted', $data['data'][0]['name']);
        static::assertSame('manual', $data['data'][0]['source']);
    }

    public function testInactiveProductNeverGetsAutoItem(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Inactive Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5,
            'active' => false
        ]);

        $this->setStockLevel($product, $location, 0);
        $this->shoppingListService->handleStockChange($product->getId());

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 0);
    }

    public function testNoActionForNonExistentProduct(): void
    {
        $fakeId = Uuid::v7();

        // Should not throw, just silently return
        $this->shoppingListService->handleStockChange($fakeId);

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 0);
    }

    public function testAutoAddWithExactlyZeroDeficit(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Stock equals minStock - no deficit
        $this->setStockLevel($product, $location, 5);
        $this->shoppingListService->handleStockChange($product->getId());

        // Should NOT create an item (deficit is 0)
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 0);
    }

    public function testAutoAddWithDeficitOfOne(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Stock is 4, minStock is 5 - deficit of exactly 1
        $this->setStockLevel($product, $location, 4);
        $this->shoppingListService->handleStockChange($product->getId());

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame(1, $data['data'][0]['amount']);
    }

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
        $this->setStockLevel($product, $location, 5);
        $this->shoppingListService->handleStockChange($product->getId());

        // Amount should stay at 5 (> not >=)
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertSame(5, $data['data'][0]['amount']);
    }

    public function testManualItemNotOverwrittenByAutoAdd(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 10
        ]);

        // Create manual item with amount 5
        $this->createItem([
            'product' => $product,
            'amount' => 5,
            'source' => ShoppingListSource::MANUAL
        ]);

        // Stock would suggest deficit of 8 (minStock 10 - stock 2 = 8)
        $this->setStockLevel($product, $location, 2);
        $this->shoppingListService->handleStockChange($product->getId());

        // Manual item should remain unchanged
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame(5, $data['data'][0]['amount']);
        static::assertSame('manual', $data['data'][0]['source']);
    }

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

    // ========== Integration with Stock API ==========

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
            'quantity' => 3
        ]);
        static::assertJsonResponse($response, Response::HTTP_OK);

        // Reconciliation already happened in-request — the auto item exists now.
        $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame('auto', $data['data'][0]['source']);
        static::assertSame(2, $data['data'][0]['amount']); // deficit 5 - 3
    }

    public function testConsumeStockReconcilesInRequest(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Create 6 stock entries
        for ($i = 0; $i < 6; $i++) {
            StockEntryFactory::createOne(['product' => $product, 'location' => $location]);
        }

        // Consume 3 entries (6 -> 3, below minStock of 5)
        $response = $this->apiPost('/stocks/consume', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 3
        ]);
        static::assertJsonResponse($response, Response::HTTP_OK);

        $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame('auto', $data['data'][0]['source']);
        static::assertSame(2, $data['data'][0]['amount']); // min 5 - remaining 3
    }

    public function testAddStockReconcilesInRequest(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Add stock
        $response = $this->apiPost('/stocks/add', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 3
        ]);
        static::assertJsonResponse($response, Response::HTTP_CREATED);

        $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame('auto', $data['data'][0]['source']);
        static::assertSame(2, $data['data'][0]['amount']); // min 5 - stock 3
    }

    // ========== Mutation Killing Tests ==========

    /**
     * When minStock is 0, no item should be created regardless of stock level.
     */
    public function testMinStockZeroNeverCreatesItem(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Zero MinStock Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 0
        ]);

        // Even with stock at 0, no item should be created when minStock is 0
        $this->setStockLevel($product, $location, 0);
        $this->shoppingListService->handleStockChange($product->getId());

        // Verify via database - no shopping list items should exist
        $this->assertDatabaseMissing(ShoppingListItem::class, [
            'product' => $product
        ]);
    }

    /**
     * When minStock is changed to 0, existing auto items should be cleaned up.
     * minStock=0 means "I don't care about this threshold anymore" - stale items removed.
     */
    public function testMinStockChangedToZeroCleansUpAutoItems(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Create an auto item (simulating previous low stock scenario)
        $autoItem = $this->createItem([
            'product' => $product,
            'amount' => 3,
            'source' => ShoppingListSource::AUTO
        ]);
        $autoItemId = $autoItem->getId();

        // Now change minStock to 0 (disable threshold)
        $product->setMinStock(0);
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->flush();

        // Trigger a stock change - with minStock=0, auto item should be cleaned up
        $this->setStockLevel($product, $location, 10);
        $this->shoppingListService->handleStockChange($product->getId());

        // Auto item should be removed - minStock=0 means no threshold, stale items cleaned up
        $this->assertDatabaseMissing(ShoppingListItem::class, ['id' => $autoItemId]);
    }

    /**
     * Kills mutant #4: max(0, deficit) → max(-1, deficit).
     * When stock exceeds minStock, deficit must be 0, not negative.
     */
    public function testNoAutoAddWhenStockExceedsMinStock(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Stock is 10, minStock is 5 - deficit would be -5, but max(0, -5) = 0
        $this->setStockLevel($product, $location, 10);
        $this->shoppingListService->handleStockChange($product->getId());

        // No item should be created (deficit is 0, not -5)
        $this->assertDatabaseMissing(ShoppingListItem::class, [
            'product' => $product
        ]);
    }

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
        $this->setStockLevel($product, $location, 7);
        $this->shoppingListService->handleStockChange($product->getId());

        // Amount should update to 3 (current deficit)
        $this->assertDatabaseHas(ShoppingListItem::class, [
            'id' => $itemId,
            'amount' => 3
        ]);
    }

    /**
     * Kills mutant #6: removes early return in upsertAutoItem.
     * When auto item exists and deficit <= existing, should not create duplicate.
     */
    public function testUpsertAutoItemDoesNotDuplicateOnNoUpdate(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 10
        ]);

        // Create auto item with amount 8
        $this->createItem([
            'product' => $product,
            'amount' => 8,
            'source' => ShoppingListSource::AUTO
        ]);

        // Trigger with lower deficit (5) - should not create new item
        $this->setStockLevel($product, $location, 5);
        $this->shoppingListService->handleStockChange($product->getId());

        // Should still have exactly 1 item
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 1);
    }

    /**
     * Verifies add reconciles to the real stock level in-request (no deficit at min).
     */
    public function testAddStockNoDeficitAtMin(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Create 3 existing entries
        for ($i = 0; $i < 3; $i++) {
            StockEntryFactory::createOne(['product' => $product, 'location' => $location]);
        }

        // Add 2 more
        $this->apiPost('/stocks/add', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 2
        ]);

        $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
        static::assertListResponse($data, 0); // 5 == min, nothing to buy
    }

    /**
     * Verifies delete reconciles to the real stock level in-request.
     */
    public function testDeleteStockReconcilesInRequest(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Create 5 entries
        $entries = [];
        for ($i = 0; $i < 5; $i++) {
            $entries[] = StockEntryFactory::createOne(['product' => $product, 'location' => $location]);
        }

        // Delete one entry
        $this->apiDelete('/stocks/entries/' . $entries[0]->getId());

        $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame('auto', $data['data'][0]['source']);
        static::assertSame(1, $data['data'][0]['amount']); // min 5 - stock 4
    }

    /**
     * Consuming below min yields exactly one auto item (no duplicates).
     */
    public function testConsumeStockReconcilesOnceInRequest(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Create 5 stock entries
        for ($i = 0; $i < 5; $i++) {
            StockEntryFactory::createOne(['product' => $product, 'location' => $location]);
        }

        // Consume 2
        $this->apiPost('/stocks/consume', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 2
        ]);

        $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
        static::assertListResponse($data, 1); // exactly one item, not duplicated
        static::assertSame('auto', $data['data'][0]['source']);
        static::assertSame(2, $data['data'][0]['amount']);
    }

    // ========== minStock Change Tests ==========

    public function testMinStockIncreaseCreatesAutoItem(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 2
        ]);

        // Create 3 stock entries - above minStock, no deficit
        for ($i = 0; $i < 3; $i++) {
            StockEntryFactory::createOne(['product' => $product, 'location' => $location]);
        }

        // Verify no shopping list item exists
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 0);

        // Update minStock to 5 - now deficit is 2
        $this->apiPut('/products/' . $product->getId(), [
            'name' => 'Test Product',
            'category_id' => (string) $category->getId(),
            'default_location_id' => (string) $location->getId(),
            'min_stock' => 5,
            'active' => true
        ]);

        // Should now have auto item with amount 2
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame(2, $data['data'][0]['amount']);
        static::assertSame('auto', $data['data'][0]['source']);
    }

    public function testRaisingMinStockViaApiAddsAutoItemInRequest(): void
    {
        $location = $this->createLocation(['name' => 'Kitchen']);
        $category = $this->createCategory(['name' => 'Dairy']);
        $product = $this->createProduct([
            'name' => 'Butter',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 1
        ]);
        $this->setStockLevel($product, $location, 2); // at min 1, no deficit

        // Raise min to 5 -> deficit 3, must reconcile in-request via the same mechanism.
        $response = $this->apiPut('/products/' . $product->getId(), [
            'name' => 'Butter',
            'category_id' => (string) $category->getId(),
            'default_location_id' => (string) $location->getId(),
            'min_stock' => 5,
            'active' => true
        ]);
        static::assertJsonResponse($response, Response::HTTP_OK);

        $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame('auto', $data['data'][0]['source']);
        static::assertSame(3, $data['data'][0]['amount']);
    }

    public function testMinStockDecreaseRemovesAutoItem(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);

        // Seed 3 stock entries - below minStock, deficit is 2
        $this->setStockLevel($product, $location, 3);
        $this->shoppingListService->handleStockChange($product->getId());

        // Verify auto item exists with amount 2
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame(2, $data['data'][0]['amount']);

        // Update minStock to 3 - now stock equals minStock, no deficit
        $this->apiPut('/products/' . $product->getId(), [
            'name' => 'Test Product',
            'category_id' => (string) $category->getId(),
            'default_location_id' => (string) $location->getId(),
            'min_stock' => 3,
            'active' => true
        ]);

        // Auto item should be removed
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 0);
    }
}
