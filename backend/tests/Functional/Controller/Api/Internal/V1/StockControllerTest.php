<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Category;
use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Factory\CategoryFactory;
use App\Factory\LocationFactory;
use App\Factory\ProductFactory;
use App\Factory\StockEntryFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

// @mago-ignore lint:too-many-methods
class StockControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /** @param array<string, mixed> $attributes */
    private function createEntry(array $attributes = []): StockEntry
    {
        return StockEntryFactory::createOne($attributes);
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

    // ========== Summary Tests ==========

    public function testSummaryReturnsEmptyArray(): void
    {
        $response = $this->apiGet('/stocks');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 0);
    }

    public function testSummaryReturnsAggregatedData(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Create 3 entries for the same product
        $this->createEntry(['product' => $product, 'location' => $location]);
        $this->createEntry(['product' => $product, 'location' => $location]);
        $this->createEntry(['product' => $product, 'location' => $location]);

        $response = $this->apiGet('/stocks');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame(3, $data['data'][0]['total_quantity']);
        static::assertSame('Test Product', $data['data'][0]['product']['name']);
    }

    public function testSummaryFiltersLowStock(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $productLow = $this->createProduct([
            'name' => 'Low Stock Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 10
        ]);
        $productOk = $this->createProduct([
            'name' => 'OK Stock Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 2
        ]);

        // Low stock: 3 entries, min is 10
        $this->createEntry(['product' => $productLow, 'location' => $location]);
        $this->createEntry(['product' => $productLow, 'location' => $location]);
        $this->createEntry(['product' => $productLow, 'location' => $location]);

        // OK stock: 5 entries, min is 2
        for ($i = 0; $i < 5; $i++) {
            $this->createEntry(['product' => $productOk, 'location' => $location]);
        }

        $response = $this->apiGet('/stocks', ['low_stock' => 'true']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame('Low Stock Product', $data['data'][0]['product']['name']);
    }

    // ========== List Entries Tests ==========

    public function testListEntriesReturnsAll(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $this->createEntry(['product' => $product, 'location' => $location]);
        $this->createEntry(['product' => $product, 'location' => $location]);

        $response = $this->apiGet('/stocks/entries');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListEntriesFiltersByLocation(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location1 = $this->createLocation(['name' => 'Kitchen']);
        $location2 = $this->createLocation(['name' => 'Pantry']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location1
        ]);

        $this->createEntry(['product' => $product, 'location' => $location1]);
        $this->createEntry(['product' => $product, 'location' => $location1]);
        $this->createEntry(['product' => $product, 'location' => $location2]);

        $response = $this->apiGet('/stocks/entries', ['location' => (string) $location1->getId()]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListEntriesFiltersByProduct(): void
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

        $this->createEntry(['product' => $product1, 'location' => $location]);
        $this->createEntry(['product' => $product2, 'location' => $location]);
        $this->createEntry(['product' => $product2, 'location' => $location]);

        $response = $this->apiGet('/stocks/entries', ['product' => (string) $product2->getId()]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    // ========== Add Stock Tests ==========

    public function testAddStockCreatesEntries(): void
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
            'quantity' => 5
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame(5, $data['data']['created']);
        static::assertCount(5, $data['data']['entries']);
    }

    public function testAddStockWithBestBefore(): void
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
            'quantity' => 2,
            'best_before' => '2026-02-15'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('2026-02-15', $data['data']['entries'][0]['best_before']);
    }

    public function testAddStockFailsWithInvalidProduct(): void
    {
        $location = $this->createLocation(['name' => 'Kitchen']);

        $response = $this->apiPost('/stocks/add', [
            'product_id' => '01936f00-0000-7000-8000-000000000000',
            'location_id' => (string) $location->getId(),
            'quantity' => 5
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('PRODUCT_NOT_FOUND', $data['type']);
    }

    // ========== Consume Stock Tests ==========

    public function testConsumeStockDeletesEntriesFifo(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Create entries with different best_before dates
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable('2026-01-20')
        ]);
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable('2026-01-25')
        ]);
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable('2026-01-30')
        ]);

        $response = $this->apiPost('/stocks/consume', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 2
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(2, $data['data']['consumed']);
        static::assertCount(2, $data['data']['deleted_entries']);
        static::assertSame(1, $data['data']['remaining_at_location']);
    }

    public function testConsumeStockFailsWithInsufficientStock(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $this->createEntry(['product' => $product, 'location' => $location]);
        $this->createEntry(['product' => $product, 'location' => $location]);

        $response = $this->apiPost('/stocks/consume', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 5
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_BAD_REQUEST);

        static::assertSame('INSUFFICIENT_STOCK', $data['type']);
    }

    // ========== Update Entry Tests ==========

    public function testUpdateEntryChangesLocation(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location1 = $this->createLocation(['name' => 'Kitchen']);
        $location2 = $this->createLocation(['name' => 'Pantry']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location1
        ]);

        $entry = $this->createEntry(['product' => $product, 'location' => $location1]);

        $response = $this->apiPatch('/stocks/entries/' . $entry->getId(), [
            'location_id' => (string) $location2->getId()
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('Pantry', $data['data']['location']['name']);
    }

    public function testUpdateEntryChangesBestBefore(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $entry = $this->createEntry(['product' => $product, 'location' => $location]);

        $response = $this->apiPatch('/stocks/entries/' . $entry->getId(), [
            'best_before' => '2026-03-15'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('2026-03-15', $data['data']['best_before']);
    }

    // ========== Delete Entry Tests ==========

    public function testDeleteEntryRemovesEntry(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $entry = $this->createEntry(['product' => $product, 'location' => $location]);

        $response = $this->apiDelete('/stocks/entries/' . $entry->getId());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // Verify it's gone
        $response = $this->apiGet('/stocks/entries/' . $entry->getId());
        static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);
    }

    public function testDeleteEntryReturns404ForMissing(): void
    {
        $response = $this->apiDelete('/stocks/entries/01936f00-0000-7000-8000-000000000000');
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('STOCK_ENTRY_NOT_FOUND', $data['type']);
    }

    // ========== Expiring Tests ==========

    public function testExpiringReturnsEntriesWithinDays(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Expiring in 3 days
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable()->modify('+3 days')
        ]);
        // Expiring in 10 days (outside default 7)
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable()->modify('+10 days')
        ]);
        // No expiry date
        $this->createEntry(['product' => $product, 'location' => $location, 'bestBefore' => null]);

        $response = $this->apiGet('/stocks/expiring', ['days' => '7']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertArrayHasKey('days_until_expiry', $data['data'][0]);
    }

    public function testExpiringIncludesAlreadyExpiredItems(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Already expired (yesterday)
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable('yesterday')
        ]);

        $response = $this->apiGet('/stocks/expiring', ['days' => '7']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertLessThan(0, $data['data'][0]['days_until_expiry']);
    }

    // ========== FIFO Edge Cases ==========

    public function testConsumeFifoNullBestBeforeConsumedLast(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Entry with NULL best_before
        $entryNull = $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => null
        ]);
        // Entry with a date
        $entryDated = $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable('2026-02-15')
        ]);

        // Consume 1 - should consume the dated one first
        $response = $this->apiPost('/stocks/consume', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 1
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(1, $data['data']['consumed']);
        static::assertContains((string) $entryDated->getId(), $data['data']['deleted_entries']);
        static::assertNotContains((string) $entryNull->getId(), $data['data']['deleted_entries']);
    }

    public function testConsumeFifoTiebreakByCreatedAt(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $sameBestBefore = new \DateTimeImmutable('2026-02-15');

        // Create entries with same best_before but different created_at
        $entryOlder = $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => $sameBestBefore,
            'createdAt' => new \DateTimeImmutable('2026-01-01 10:00:00')
        ]);
        $entryNewer = $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => $sameBestBefore,
            'createdAt' => new \DateTimeImmutable('2026-01-01 12:00:00')
        ]);

        // Consume 1 - should consume the older created_at first
        $response = $this->apiPost('/stocks/consume', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 1
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(1, $data['data']['consumed']);
        static::assertContains((string) $entryOlder->getId(), $data['data']['deleted_entries']);
        static::assertNotContains((string) $entryNewer->getId(), $data['data']['deleted_entries']);
    }

    // ========== Auto-calculated Best Before ==========

    public function testAddStockAutoCalculatesBestBeforeFromProduct(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'defaultExpiryDays' => 14
        ]);

        $response = $this->apiPost('/stocks/add', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 1
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        $expectedDate = new \DateTimeImmutable()
            ->modify('+14 days')
            ->format('Y-m-d');
        static::assertSame($expectedDate, $data['data']['entries'][0]['best_before']);
    }
}
