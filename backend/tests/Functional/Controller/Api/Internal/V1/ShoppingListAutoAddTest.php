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
use App\Message\StockChangedMessage;
use App\Service\ShoppingListService;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
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

        // Simulate stock change: was 3, now 2
        $this->shoppingListService->handleStockChange($product->getId(), 3, 2);

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

        // First change: stock is 8, deficit is 2
        $this->shoppingListService->handleStockChange($product->getId(), 10, 8);

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertSame(2, $data['data'][0]['amount']);

        // Second change: stock is 3, deficit is 7
        $this->shoppingListService->handleStockChange($product->getId(), 8, 3);

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertSame(7, $data['data'][0]['amount']);
    }

    public function testAutoAddNeverDecreasesAmount(): void
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

        // Second change: stock is 5, deficit is 5 (less than current amount)
        $this->shoppingListService->handleStockChange($product->getId(), 2, 5);

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        // Should keep 8, not reduce to 5
        static::assertSame(8, $data['data'][0]['amount']);
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
        $this->shoppingListService->handleStockChange($product->getId(), 3, 5);

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
        $this->shoppingListService->handleStockChange($product->getId(), 3, 10);

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
        $this->shoppingListService->handleStockChange($product->getId(), 3, 5);

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
        $this->shoppingListService->handleStockChange($product->getId(), 3, 5);

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

        // Stock change on product with no minStock
        $this->shoppingListService->handleStockChange($product->getId(), 5, 0);

        // No shopping item should be created
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 0);
    }

    public function testNoActionForInactiveProduct(): void
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

        // Stock change on inactive product
        $this->shoppingListService->handleStockChange($product->getId(), 5, 0);

        // No shopping item should be created
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 0);
    }

    public function testNoActionForNonExistentProduct(): void
    {
        $fakeId = Uuid::v7();

        // Should not throw, just silently return
        $this->shoppingListService->handleStockChange($fakeId, 5, 0);

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
        $this->shoppingListService->handleStockChange($product->getId(), 3, 5);

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
        $this->shoppingListService->handleStockChange($product->getId(), 5, 4);

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
        $this->shoppingListService->handleStockChange($product->getId(), 3, 5);

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

        // Stock change would suggest deficit of 8
        $this->shoppingListService->handleStockChange($product->getId(), 10, 2);

        // Manual item should remain unchanged
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame(5, $data['data'][0]['amount']);
        static::assertSame('manual', $data['data'][0]['source']);
    }

    // ========== Integration with Stock API ==========

    public function testConsumeStockDispatchesMessage(): void
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

        // Check that a message was dispatched (in test env, transport is in-memory)
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $messages = $transport->getSent();

        static::assertGreaterThanOrEqual(1, count($messages));
    }

    public function testAddStockDispatchesMessage(): void
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

        // Check that a message was dispatched
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $messages = $transport->getSent();

        static::assertGreaterThanOrEqual(1, count($messages));
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
        $this->shoppingListService->handleStockChange($product->getId(), 10, 0);

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
        $this->shoppingListService->handleStockChange($product->getId(), 5, 10);

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
        $this->shoppingListService->handleStockChange($product->getId(), 3, 10);

        // No item should be created (deficit is 0, not -5)
        $this->assertDatabaseMissing(ShoppingListItem::class, [
            'product' => $product
        ]);
    }

    /**
     * Kills mutant #5: deficit > existing → deficit >= existing.
     * When deficit equals existing amount, quantity should NOT update.
     */
    public function testAutoAddDoesNotUpdateWhenDeficitEqualsExisting(): void
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

        // Stock is 5, minStock is 10 - deficit is exactly 5 (same as existing)
        $this->shoppingListService->handleStockChange($product->getId(), 3, 5);

        // Amount should stay at 5, not be updated (> not >=)
        $this->assertDatabaseHas(ShoppingListItem::class, [
            'id' => $itemId,
            'amount' => 5
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
        $this->shoppingListService->handleStockChange($product->getId(), 3, 5);

        // Should still have exactly 1 item
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 1);
    }

    /**
     * Kills mutants #14, #17-19: Verifies exact newQty calculation in messages.
     * For addStock: newQty = previousQty + quantity
     */
    public function testAddStockDispatchesMessageWithCorrectQuantities(): void
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

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $messages = $transport->getSent();

        static::assertCount(1, $messages);
        /** @var Envelope $envelope */
        $envelope = $messages[0];
        /** @var StockChangedMessage $message */
        $message = $envelope->getMessage();

        static::assertInstanceOf(StockChangedMessage::class, $message);
        static::assertSame(3, $message->previousQuantity); // Was 3
        static::assertSame(5, $message->newQuantity); // Now 3 + 2 = 5
    }

    /**
     * Kills mutants #17-19: Verifies exact newQty calculation for deleteEntry.
     * For deleteEntry: newQty = previousQty - 1
     */
    public function testDeleteEntryDispatchesMessageWithCorrectQuantities(): void
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

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $messages = $transport->getSent();

        static::assertCount(1, $messages);
        /** @var Envelope $envelope */
        $envelope = $messages[0];
        /** @var StockChangedMessage $message */
        $message = $envelope->getMessage();

        static::assertInstanceOf(StockChangedMessage::class, $message);
        static::assertSame(5, $message->previousQuantity); // Was 5
        static::assertSame(4, $message->newQuantity); // Now 5 - 1 = 4
    }

    /**
     * Kills mutant #20: Verifies dispatch() is actually called.
     */
    public function testConsumeStockDispatchesExactlyOneMessage(): void
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

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $messages = $transport->getSent();

        // Must have exactly 1 message
        static::assertCount(1, $messages);
        static::assertInstanceOf(StockChangedMessage::class, $messages[0]->getMessage());
    }
}
