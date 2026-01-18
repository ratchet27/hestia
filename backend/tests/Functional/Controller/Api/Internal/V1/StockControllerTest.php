<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Category;
use App\Entity\Location;
use App\Entity\Product;
use App\Entity\Stock;
use App\Factory\CategoryFactory;
use App\Factory\LocationFactory;
use App\Factory\ProductFactory;
use App\Factory\StockFactory;
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

    /**
     * @param array<string, mixed> $attributes
     */
    private function createStock(array $attributes = []): Stock
    {
        return StockFactory::createOne($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createProduct(array $attributes = []): Product
    {
        return ProductFactory::createOne($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createCategory(array $attributes = []): Category
    {
        return CategoryFactory::createOne($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createLocation(array $attributes = []): Location
    {
        return LocationFactory::createOne($attributes);
    }

    // ========== List Tests ==========

    public function testListStocksReturnsEmptyArray(): void
    {
        $response = $this->apiGet('/stocks');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 0);
    }

    public function testListStocksReturnsStockEntries(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        $this->createStock([
            'product' => $product,
            'location' => $location,
            'quantity' => 10
        ]);

        $response = $this->apiGet('/stocks');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame(10, $data['data'][0]['quantity']);
        static::assertSame('Test Product', $data['data'][0]['product']['name']);
    }

    public function testListStocksFiltersByLocation(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location1 = $this->createLocation(['name' => 'Kitchen']);
        $location2 = $this->createLocation(['name' => 'Pantry']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location1
        ]);
        $this->createStock(['product' => $product, 'location' => $location1, 'quantity' => 5]);
        $this->createStock(['product' => $product, 'location' => $location2, 'quantity' => 3]);

        $response = $this->apiGet('/stocks', ['location' => (string) $location1->getId()]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame(5, $data['data'][0]['quantity']);
    }

    public function testListStocksFiltersLowStock(): void
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
            'minStock' => 5
        ]);
        $this->createStock(['product' => $productLow, 'location' => $location, 'quantity' => 3]);
        $this->createStock(['product' => $productOk, 'location' => $location, 'quantity' => 10]);

        $response = $this->apiGet('/stocks', ['low_stock' => 'true']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame('Low Stock Product', $data['data'][0]['product']['name']);
    }

    // ========== Movement Tests ==========

    public function testCreateMovementAddCreatesStockAndMovement(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $response = $this->apiPost('/stocks/movements', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'type' => 'ADD',
            'quantity' => 5,
            'notes' => 'Initial stock'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('ADD', $data['data']['type']);
        static::assertSame(5, $data['data']['quantity']);
        static::assertSame(5, $data['data']['stock']['quantity']);
        static::assertSame('Initial stock', $data['data']['notes']);
    }

    public function testCreateMovementRemoveDecreasesQuantity(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        $this->createStock(['product' => $product, 'location' => $location, 'quantity' => 10]);

        $response = $this->apiPost('/stocks/movements', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'type' => 'REMOVE',
            'quantity' => 3
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame(7, $data['data']['stock']['quantity']);
    }

    public function testCreateMovementAdjustSetsAbsoluteQuantity(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        $this->createStock(['product' => $product, 'location' => $location, 'quantity' => 10]);

        $response = $this->apiPost('/stocks/movements', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'type' => 'ADJUST',
            'quantity' => 3
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame(3, $data['data']['stock']['quantity']);
    }

    public function testCreateMovementRemoveFailsWithInsufficientStock(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);
        $this->createStock(['product' => $product, 'location' => $location, 'quantity' => 5]);

        $response = $this->apiPost('/stocks/movements', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'type' => 'REMOVE',
            'quantity' => 10
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_BAD_REQUEST);

        static::assertSame('INSUFFICIENT_STOCK', $data['type']);
    }

    public function testCreateMovementFailsWithInvalidProduct(): void
    {
        $location = $this->createLocation(['name' => 'Kitchen']);

        $response = $this->apiPost('/stocks/movements', [
            'product_id' => '01936f00-0000-7000-8000-000000000000',
            'location_id' => (string) $location->getId(),
            'type' => 'ADD',
            'quantity' => 5
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('Product not found', $data['title']);
        static::assertSame('PRODUCT_NOT_FOUND', $data['type']);
    }

    public function testCreateMovementFailsWithInvalidLocation(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $response = $this->apiPost('/stocks/movements', [
            'product_id' => (string) $product->getId(),
            'location_id' => '01936f00-0000-7000-8000-000000000000',
            'type' => 'ADD',
            'quantity' => 5
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_BAD_REQUEST);

        static::assertSame('Location not found', $data['title']);
        static::assertSame('LOCATION_NOT_FOUND', $data['type']);
    }

    public function testCreateMovementFailsWithNegativeQuantity(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Kitchen']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location
        ]);

        $response = $this->apiPost('/stocks/movements', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'type' => 'ADD',
            'quantity' => -5
        ]);

        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }
}
