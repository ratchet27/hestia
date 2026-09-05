<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Category;
use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\StockEntryFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

// @mago-ignore lint:too-many-methods
class StockControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loginAs(UserFactory::createOne());
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

    /**
     * Regression for C1 (#53): between 00:00–05:00 Almaty the API must report the household day,
     * not UTC. At 22:30Z (= 03:30 on 2026-06-06 Almaty) an item dated 2026-06-06 is "today" (0),
     * not "tomorrow" (1). Mirrors testExpiringDaysUntilExpiryUsesHouseholdTimezone for the
     * entry-list path (/stocks/entries → StockEntryResponse::fromEntity).
     */
    public function testListEntriesDaysUntilExpiryUsesHouseholdTimezone(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-05 22:30:00', new \DateTimeZone('UTC')));

        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable('2026-06-06')
        ]);

        $response = $this->apiGet('/stocks/entries');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame(0, $data['data'][0]['days_until_expiry']);
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
        static::assertNotEmpty($data['errors']);
        static::assertSame('quantity', $data['errors'][0]['property']);
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
        $data = static::assertErrorResponse($response, Response::HTTP_CONFLICT);

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

    public function testExpiringRejectsNegativeDays(): void
    {
        $response = $this->apiGet('/stocks/expiring', ['days' => '-5']);
        $data = static::assertErrorResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);

        static::assertSame('VALIDATION_ERROR', $data['type']);
        static::assertNotEmpty($data['errors']);
        static::assertSame('days', $data['errors'][0]['property']);
    }

    public function testExpiringAllowsZeroDays(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Already expired (yesterday) — must be included by a days=0 cutoff of "today".
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable('yesterday')
        ]);

        $response = $this->apiGet('/stocks/expiring', ['days' => '0']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
    }

    /**
     * Regression for C1 (#53): between 00:00–05:00 Almaty the API must report the household day,
     * not UTC. At 22:30Z (= 03:30 on 2026-06-06 Almaty) an item dated 2026-06-06 is "today" (0),
     * not "tomorrow" (1).
     */
    public function testExpiringDaysUntilExpiryUsesHouseholdTimezone(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-05 22:30:00', new \DateTimeZone('UTC')));

        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable('2026-06-06')
        ]);

        $response = $this->apiGet('/stocks/expiring', ['days' => '7']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame(0, $data['data'][0]['days_until_expiry']);
    }

    /**
     * Locks the cutoff inclusivity (findExpiring uses `bestBefore <= cutoff`, cutoff bound as DATE):
     * at 22:30Z (= 2026-06-06 Almaty), days=7 -> cutoff 2026-06-13. An item dated exactly on the
     * cutoff is included (delta 7); an item one day past it is excluded.
     */
    public function testExpiringIncludesItemExactlyOnCutoffAndExcludesBeyond(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-05 22:30:00', new \DateTimeZone('UTC')));

        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        // Exactly on the cutoff (today + 7 = 2026-06-13) -> included.
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable('2026-06-13')
        ]);
        // One day past the cutoff -> excluded.
        $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => new \DateTimeImmutable('2026-06-14')
        ]);

        $response = $this->apiGet('/stocks/expiring', ['days' => '7']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame('2026-06-13', $data['data'][0]['best_before']);
        static::assertSame(7, $data['data'][0]['days_until_expiry']);
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

        // Create entries with same best_before but different created_at using MockClock
        static::mockTime(new \DateTimeImmutable('2026-01-01 10:00:00'));
        $entryOlder = $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => $sameBestBefore
        ]);

        static::mockTime(new \DateTimeImmutable('2026-01-01 12:00:00'));
        $entryNewer = $this->createEntry([
            'product' => $product,
            'location' => $location,
            'bestBefore' => $sameBestBefore
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
        // 22:30Z == 03:30 on 2026-06-06 Almaty -> "today" is the 6th; +14d = 2026-06-20.
        static::mockTime(new \DateTimeImmutable('2026-06-05 22:30:00', new \DateTimeZone('UTC')));

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

        static::assertSame('2026-06-20', $data['data']['entries'][0]['best_before']);
    }

    // ========== Mutation Killing Tests ==========

    /**
     * Kills mutant #11: Removes default => null arm in match.
     * Tests that product without defaultExpiryDays and no explicit best_before gets null.
     */
    public function testAddStockWithoutExpiryGetsNullBestBefore(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'No Expiry Product',
            'category' => $category,
            'defaultLocation' => $location,
            'defaultExpiryDays' => null
        ]);

        $response = $this->apiPost('/stocks/add', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 1
            // No best_before provided
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        // best_before should be null (default arm of match)
        static::assertNull($data['data']['entries'][0]['best_before']);
    }

    /**
     * Kills mutant: Removes ?-> null-safe operator in StockEntryResponse::fromEntity.
     * Verifies entries with null best_before are returned correctly via list endpoint.
     */
    public function testListEntriesReturnsNullBestBeforeCorrectly(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'No Expiry Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Create entry with explicit null bestBefore
        $this->createEntry(['product' => $product, 'location' => $location, 'bestBefore' => null]);

        $response = $this->apiGet('/stocks/entries');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertNull($data['data'][0]['best_before']);
        static::assertNull($data['data'][0]['days_until_expiry']);
    }

    /**
     * Kills mutants #12, #13: Removes persist() and flush() in addStock.
     * Verifies entries are actually saved to database.
     */
    public function testAddStockPersistsEntriesToDatabase(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Persistence Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $response = $this->apiPost('/stocks/add', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 3
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        // Verify all 3 entries exist in database
        foreach ($data['data']['entries'] as $entry) {
            $this->assertDatabaseHas(StockEntry::class, [
                'id' => $entry['id']
            ]);
        }
    }

    /**
     * Kills mutant #15: available < quantity → available <= quantity.
     * Tests consuming exactly the available amount succeeds.
     */
    public function testConsumeExactlyAvailableQuantitySucceeds(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        // Create exactly 5 entries
        for ($i = 0; $i < 5; $i++) {
            $this->createEntry(['product' => $product, 'location' => $location]);
        }

        // Consume exactly 5 - should succeed (available == quantity)
        $response = $this->apiPost('/stocks/consume', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 5
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(5, $data['data']['consumed']);
        static::assertSame(0, $data['data']['remaining_at_location']);
    }

    /**
     * Kills mutant #16: Removes flush() in updateEntry.
     * Verifies updates are persisted to database.
     */
    public function testUpdateEntryPersistsToDatabase(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location1 = $this->createLocation(['name' => 'Kitchen']);
        $location2 = $this->createLocation(['name' => 'Pantry']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location1
        ]);

        $entry = $this->createEntry([
            'product' => $product,
            'location' => $location1,
            'bestBefore' => new \DateTimeImmutable('2026-01-15')
        ]);

        // Update location and best_before
        $this->apiPatch('/stocks/entries/' . $entry->getId(), [
            'location_id' => (string) $location2->getId(),
            'best_before' => '2026-06-15'
        ]);

        // Verify changes are persisted in database
        $this->assertDatabaseHas(StockEntry::class, [
            'id' => $entry->getId(),
            'location' => $location2
        ]);
    }

    /**
     * Kills mutant #21: lowStockOnly = false → true.
     * Tests that default (no param) returns ALL products with stock, not just low stock.
     */
    public function testSummaryDefaultReturnsAllProducts(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);

        // Product with adequate stock (10 entries, minStock = 5)
        $productOk = $this->createProduct([
            'name' => 'OK Stock Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);
        for ($i = 0; $i < 10; $i++) {
            $this->createEntry(['product' => $productOk, 'location' => $location]);
        }

        // Product with low stock (2 entries, minStock = 5)
        $productLow = $this->createProduct([
            'name' => 'Low Stock Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);
        $this->createEntry(['product' => $productLow, 'location' => $location]);
        $this->createEntry(['product' => $productLow, 'location' => $location]);

        // Default call (no low_stock param) should return BOTH products
        $response = $this->apiGet('/stocks');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    /**
     * Kills mutants #22, #23: array_map unwrap and return slice.
     * Tests that all products are returned with correct structure.
     */
    public function testSummaryReturnsAllProductsWithCorrectStructure(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);

        // Create 3 products with stock
        for ($i = 1; $i <= 3; $i++) {
            $product = $this->createProduct([
                'name' => 'Product ' . $i,
                'category' => $category,
                'defaultLocation' => $location
            ]);
            $this->createEntry(['product' => $product, 'location' => $location]);
        }

        $response = $this->apiGet('/stocks');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        // Should return all 3 products
        static::assertListResponse($data, 3);

        // Each item should have transformed location structure (not raw array)
        foreach ($data['data'] as $item) {
            static::assertArrayHasKey('locations', $item);
            static::assertIsArray($item['locations']);
            // If array_map was removed, locations would be raw DB data
            if ($item['locations'] !== []) {
                static::assertArrayHasKey('id', $item['locations'][0]);
                static::assertArrayHasKey('name', $item['locations'][0]);
                static::assertArrayHasKey('quantity', $item['locations'][0]);
            }
        }
    }

    public function testListEntriesRejectsMalformedLocation(): void
    {
        $response = $this->apiGet('/stocks/entries', ['location' => 'not-a-uuid']);
        $data = static::assertErrorResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);

        static::assertSame('VALIDATION_ERROR', $data['type']);
        static::assertSame('location', $data['errors'][0]['property']);
    }

    public function testListEntriesRejectsMalformedProduct(): void
    {
        $response = $this->apiGet('/stocks/entries', ['product' => '123']);
        $data = static::assertErrorResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);

        static::assertSame('VALIDATION_ERROR', $data['type']);
        static::assertSame('product', $data['errors'][0]['property']);
    }
}
