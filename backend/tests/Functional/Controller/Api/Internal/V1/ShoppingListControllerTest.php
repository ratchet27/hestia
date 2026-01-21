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
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

// @mago-ignore lint:too-many-methods
class ShoppingListControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /** @param array<string, mixed> $attributes */
    private function createItem(array $attributes = []): ShoppingListItem
    {
        return ShoppingListItemFactory::createOne($attributes);
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

    // ========== List Tests ==========

    public function testIndexReturnsEmptyArray(): void
    {
        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 0);
    }

    public function testIndexReturnsAllItems(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product1 = $this->createProduct([
            'name' => 'Product 1',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        $product2 = $this->createProduct([
            'name' => 'Product 2',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $this->createItem(['product' => $product1, 'amount' => 2]);
        $this->createItem(['product' => $product2, 'amount' => 3]);

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testIndexOrdersByDoneThenCreatedAt(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product1 = $this->createProduct([
            'name' => 'Done Item',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        $product2 = $this->createProduct([
            'name' => 'Pending Item',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $this->createItem(['product' => $product1, 'done' => true]);
        $this->createItem(['product' => $product2, 'done' => false]);

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        // Pending items should come first (done=false sorted before done=true)
        static::assertFalse($data['data'][0]['done']);
        static::assertTrue($data['data'][1]['done']);
    }

    // ========== Show Tests ==========

    public function testShowReturnsItem(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $item = $this->createItem(['product' => $product, 'amount' => 5, 'note' => 'Test note']);

        $response = $this->apiGet('/shopping-list/' . $item->getId());
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('Test Product', $data['data']['name']);
        static::assertSame(5, $data['data']['amount']);
        static::assertSame('Test note', $data['data']['note']);
    }

    public function testShowReturns404ForMissing(): void
    {
        $response = $this->apiGet('/shopping-list/01936f00-0000-7000-8000-000000000000');
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('SHOPPING_LIST_ITEM_NOT_FOUND', $data['type']);
    }

    // ========== Create Tests ==========

    public function testCreateWithProduct(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $response = $this->apiPost('/shopping-list', [
            'product_id' => (string) $product->getId(),
            'amount' => 3,
            'note' => 'Buy fresh'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('Test Product', $data['data']['name']);
        static::assertSame(3, $data['data']['amount']);
        static::assertSame('Buy fresh', $data['data']['note']);
        static::assertSame('manual', $data['data']['source']);
    }

    public function testCreateWithCustomName(): void
    {
        $response = $this->apiPost('/shopping-list', [
            'custom_name' => 'Fresh bread',
            'amount' => 1
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('Fresh bread', $data['data']['name']);
        static::assertNull($data['data']['product_id']);
    }

    public function testCreateMergesExistingProduct(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Create existing item with amount 2
        $this->createItem([
            'product' => $product,
            'amount' => 2,
            'source' => ShoppingListSource::AUTO
        ]);

        // Add same product with amount 5
        $response = $this->apiPost('/shopping-list', [
            'product_id' => (string) $product->getId(),
            'amount' => 5
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        // Should use max(2, 5) = 5 and convert to manual
        static::assertSame(5, $data['data']['amount']);
        static::assertSame('manual', $data['data']['source']);

        // Should only have 1 item total
        $listResponse = $this->apiGet('/shopping-list');
        $listData = static::assertJsonResponse($listResponse, Response::HTTP_OK);
        static::assertListResponse($listData, 1);
    }

    public function testCreateMergesUsesMaxAmount(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Create existing item with amount 10
        $this->createItem(['product' => $product, 'amount' => 10]);

        // Add same product with amount 3 (less than existing)
        $response = $this->apiPost('/shopping-list', [
            'product_id' => (string) $product->getId(),
            'amount' => 3
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        // Should keep max(10, 3) = 10
        static::assertSame(10, $data['data']['amount']);
    }

    public function testCreateMergesUpdatesNote(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Create existing item with no note
        $this->createItem(['product' => $product, 'amount' => 2, 'note' => null]);

        // Add same product with a note
        $response = $this->apiPost('/shopping-list', [
            'product_id' => (string) $product->getId(),
            'amount' => 1,
            'note' => 'New note added'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        // Note should be updated
        static::assertSame('New note added', $data['data']['note']);
    }

    public function testCreateMergesDoesNotOverwriteNoteWithNull(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Create existing item with a note
        $this->createItem(['product' => $product, 'amount' => 2, 'note' => 'Original note']);

        // Add same product without a note
        $response = $this->apiPost('/shopping-list', [
            'product_id' => (string) $product->getId(),
            'amount' => 1
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        // Original note should be preserved
        static::assertSame('Original note', $data['data']['note']);
    }

    public function testCreateNewItemHasManualSource(): void
    {
        $response = $this->apiPost('/shopping-list', [
            'custom_name' => 'Test Item',
            'amount' => 1
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('manual', $data['data']['source']);
    }

    // ========== Update Tests ==========

    public function testUpdateAmount(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $item = $this->createItem(['product' => $product, 'amount' => 2]);

        $response = $this->apiPatch('/shopping-list/' . $item->getId(), [
            'amount' => 7
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(7, $data['data']['amount']);
    }

    public function testUpdateDone(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $item = $this->createItem(['product' => $product, 'done' => false]);

        $response = $this->apiPatch('/shopping-list/' . $item->getId(), [
            'done' => true
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertTrue($data['data']['done']);
    }

    public function testUpdateNote(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $item = $this->createItem(['product' => $product, 'note' => null]);

        $response = $this->apiPatch('/shopping-list/' . $item->getId(), [
            'note' => 'Updated note'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('Updated note', $data['data']['note']);
    }

    public function testUpdateReturns404ForMissing(): void
    {
        $response = $this->apiPatch('/shopping-list/01936f00-0000-7000-8000-000000000000', [
            'amount' => 5
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('SHOPPING_LIST_ITEM_NOT_FOUND', $data['type']);
    }

    public function testUpdateAmountConvertsAutoToManual(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Auto Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Create AUTO item
        $item = $this->createItem([
            'product' => $product,
            'amount' => 5,
            'source' => ShoppingListSource::AUTO
        ]);

        // Update amount
        $response = $this->apiPatch('/shopping-list/' . $item->getId(), [
            'amount' => 3
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
            'defaultLocation' => $location
        ]);

        // Create AUTO item
        $item = $this->createItem([
            'product' => $product,
            'amount' => 5,
            'source' => ShoppingListSource::AUTO
        ]);

        // Update only note
        $response = $this->apiPatch('/shopping-list/' . $item->getId(), [
            'note' => 'Buy the organic one'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        // Should remain AUTO
        static::assertSame(5, $data['data']['amount']);
        static::assertSame('auto', $data['data']['source']);
    }

    public function testUpdatePersistsChanges(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $item = $this->createItem(['product' => $product, 'amount' => 2, 'note' => null, 'done' => false]);

        // Update multiple fields
        $this->apiPatch('/shopping-list/' . $item->getId(), [
            'amount' => 10,
            'note' => 'Updated note',
            'done' => true
        ]);

        // Verify changes are persisted to database
        $this->assertDatabaseHas(ShoppingListItem::class, [
            'id' => $item->getId(),
            'amount' => 10,
            'note' => 'Updated note',
            'done' => true
        ]);
    }

    // ========== Delete Tests ==========

    public function testDeleteRemovesItem(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $item = $this->createItem(['product' => $product]);
        $itemId = $item->getId();

        $response = $this->apiDelete('/shopping-list/' . $itemId);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // Verify it's removed from database
        $this->assertDatabaseMissing(ShoppingListItem::class, ['id' => $itemId]);
    }

    public function testDeleteReturns404ForMissing(): void
    {
        $response = $this->apiDelete('/shopping-list/01936f00-0000-7000-8000-000000000000');
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('SHOPPING_LIST_ITEM_NOT_FOUND', $data['type']);
    }

    // ========== Clear Completed Tests ==========

    public function testClearCompletedRemovesDoneItems(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product1 = $this->createProduct([
            'name' => 'Done Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        $product2 = $this->createProduct([
            'name' => 'Pending Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $doneItem1 = $this->createItem(['product' => $product1, 'done' => true]);
        $doneItem2 = $this->createItem(['product' => $product1, 'done' => true]);
        $pendingItem = $this->createItem(['product' => $product2, 'done' => false]);

        $response = $this->apiPost('/shopping-list/clear-completed', []);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(2, $data['data']['cleared']);

        // Verify done items are removed from database
        $this->assertDatabaseMissing(ShoppingListItem::class, ['id' => $doneItem1->getId()]);
        $this->assertDatabaseMissing(ShoppingListItem::class, ['id' => $doneItem2->getId()]);

        // Verify pending item remains in database
        $this->assertDatabaseHas(ShoppingListItem::class, ['id' => $pendingItem->getId(), 'done' => false]);
    }

    public function testClearCompletedReturnsZeroWhenNoDoneItems(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Pending Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $this->createItem(['product' => $product, 'done' => false]);

        $response = $this->apiPost('/shopping-list/clear-completed', []);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(0, $data['data']['cleared']);
    }

    // ========== Source Types Tests ==========

    public function testItemSourceTypesArePreserved(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product1 = $this->createProduct([
            'name' => 'Manual Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        $product2 = $this->createProduct([
            'name' => 'Auto Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        $product3 = $this->createProduct([
            'name' => 'Recipe Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $this->createItem(['product' => $product1, 'source' => ShoppingListSource::MANUAL]);
        $this->createItem(['product' => $product2, 'source' => ShoppingListSource::AUTO]);
        $this->createItem(['product' => $product3, 'source' => ShoppingListSource::RECIPE]);

        $response = $this->apiGet('/shopping-list');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        $sources = array_column($data['data'], 'source');
        static::assertContains('manual', $sources);
        static::assertContains('auto', $sources);
        static::assertContains('recipe', $sources);
    }

    // ========== Mutation Killing Tests ==========

    /**
     * Kills mutant #7: Removes flush() in merge path.
     * Verifies data is actually persisted after merging.
     */
    public function testCreateMergePersistsToDatabase(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Create existing auto item
        $this->createItem([
            'product' => $product,
            'amount' => 2,
            'source' => ShoppingListSource::AUTO
        ]);

        // Merge via API - this should persist the changes
        $this->apiPost('/shopping-list', [
            'product_id' => (string) $product->getId(),
            'amount' => 5
        ]);

        // Verify in database (not just API response)
        $this->assertDatabaseHas(ShoppingListItem::class, [
            'product' => $product,
            'amount' => 5,
            'source' => 'manual'
        ]);
    }

    /**
     * Kills mutant #8: Removes setSource(MANUAL).
     * Verifies source is set to manual for new items.
     */
    public function testCreateNewItemSetsSourceToManualInDatabase(): void
    {
        $response = $this->apiPost('/shopping-list', [
            'custom_name' => 'Database Test Item',
            'amount' => 3
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        // Verify source in database
        $this->assertDatabaseHas(ShoppingListItem::class, [
            'id' => $data['data']['id'],
            'source' => 'manual'
        ]);
    }

    /**
     * Kills mutants #9, #10: Removes persist() and flush() for new items.
     * Verifies new items are actually saved to database.
     */
    public function testCreateNewItemPersistsToDatabase(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Persistence Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $response = $this->apiPost('/shopping-list', [
            'product_id' => (string) $product->getId(),
            'amount' => 7,
            'note' => 'Test note'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        // Verify item exists in database with all fields
        $this->assertDatabaseHas(ShoppingListItem::class, [
            'id' => $data['data']['id'],
            'product' => $product,
            'amount' => 7,
            'note' => 'Test note',
            'source' => 'manual'
        ]);
    }
}
