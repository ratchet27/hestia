<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Factory\BarcodeFactory;
use App\Factory\CategoryFactory;
use App\Factory\LocationFactory;
use App\Factory\ProductFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

// @mago-ignore lint:too-many-methods
class ProductControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    // ========== List Tests ==========

    public function testListReturnsEmptyWhenNoData(): void
    {
        $response = $this->apiGet('/products');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 0);
    }

    public function testListReturnsProducts(): void
    {
        ProductFactory::createMany(3);

        $response = $this->apiGet('/products');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 3);
    }

    public function testListFilterByName(): void
    {
        ProductFactory::createOne(['name' => 'Apple Juice']);
        ProductFactory::createOne(['name' => 'Orange Juice']);
        ProductFactory::createOne(['name' => 'Milk']);

        $response = $this->apiGet('/products', ['name' => 'Juice']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListFilterByCategoryId(): void
    {
        $category1 = CategoryFactory::createOne(['name' => 'Beverages']);
        $category2 = CategoryFactory::createOne(['name' => 'Dairy']);

        ProductFactory::createOne(['category' => $category1]);
        ProductFactory::createOne(['category' => $category1]);
        ProductFactory::createOne(['category' => $category2]);

        $response = $this->apiGet('/products', ['category_id' => $category1->getId()->toRfc4122()]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListFilterByActive(): void
    {
        ProductFactory::createOne(['active' => true]);
        ProductFactory::createOne(['active' => true]);
        ProductFactory::createOne(['active' => false]);

        $response = $this->apiGet('/products', ['active' => '1']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListFilterByInactive(): void
    {
        ProductFactory::createOne(['active' => true]);
        ProductFactory::createOne(['active' => false]);

        $response = $this->apiGet('/products', ['active' => '0']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
    }

    public function testListCombinedFilters(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Beverages']);

        ProductFactory::createOne(['name' => 'Apple Juice', 'category' => $category, 'active' => true]);
        ProductFactory::createOne(['name' => 'Orange Juice', 'category' => $category, 'active' => false]);
        ProductFactory::createOne(['name' => 'Apple Cider', 'active' => true]);

        $response = $this->apiGet('/products', [
            'name' => 'Apple',
            'category_id' => $category->getId()->toRfc4122(),
            'active' => '1'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame('Apple Juice', $data['data'][0]['name']);
    }

    // ========== Show Tests ==========

    public function testShowReturnsProduct(): void
    {
        $product = ProductFactory::createOne(['name' => 'Test Product']);

        $response = $this->apiGet('/products/' . $product->getId()->toRfc4122());
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertArrayHasKey('data', $data);
        static::assertSame('Test Product', $data['data']['name']);
    }

    public function testShowReturnsNotFoundForMissingProduct(): void
    {
        $response = $this->apiGet('/products/' . Uuid::v7()->toRfc4122());
        $data = static::assertJsonResponse($response, Response::HTTP_NOT_FOUND);

        static::assertArrayHasKey('detail', $data);
    }

    public function testShowReturnsBadRequestForInvalidUuid(): void
    {
        $response = $this->apiGet('/products/not-a-uuid');
        $data = static::assertJsonResponse($response, Response::HTTP_BAD_REQUEST);

        static::assertArrayHasKey('detail', $data);
        static::assertSame('Invalid UUID format', $data['detail']);
    }

    public function testShowIncludesBarcodes(): void
    {
        $product = ProductFactory::createOne(['name' => 'Test Product']);
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product]);
        BarcodeFactory::createOne(['barcode' => '9876543210987', 'product' => $product]);

        $response = $this->apiGet('/products/' . $product->getId()->toRfc4122());
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertArrayHasKey('barcodes', $data['data']);
        static::assertCount(2, $data['data']['barcodes']);
    }

    public function testShowResponseStructure(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $location = LocationFactory::createOne(['name' => 'Test Location']);
        $product = ProductFactory::createOne([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'defaultExpiryDays' => 30,
            'minStock' => 5,
            'active' => true
        ]);

        $response = $this->apiGet('/products/' . $product->getId()->toRfc4122());
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        $productData = $data['data'];
        static::assertArrayHasKey('id', $productData);
        static::assertArrayHasKey('name', $productData);
        static::assertArrayHasKey('category', $productData);
        static::assertArrayHasKey('default_location', $productData);
        static::assertArrayHasKey('default_expiry_days', $productData);
        static::assertArrayHasKey('min_stock', $productData);
        static::assertArrayHasKey('active', $productData);
        static::assertArrayHasKey('created_at', $productData);
        static::assertArrayHasKey('updated_at', $productData);
        static::assertArrayHasKey('barcodes', $productData);

        static::assertSame('Test Product', $productData['name']);
        static::assertSame('Test Category', $productData['category']['name']);
        static::assertSame('Test Location', $productData['default_location']['name']);
        static::assertSame(30, $productData['default_expiry_days']);
        static::assertSame(5, $productData['min_stock']);
        static::assertTrue($productData['active']);
    }

    // ========== Create Tests ==========

    public function testCreateProductWithMinimalFields(): void
    {
        $category = CategoryFactory::createOne();
        $location = LocationFactory::createOne();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId()->toRfc4122(),
            'default_location_id' => $location->getId()->toRfc4122()
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertArrayHasKey('data', $data);
        static::assertSame('New Product', $data['data']['name']);
        static::assertSame(0, $data['data']['min_stock']);
        static::assertTrue($data['data']['active']);
        static::assertNull($data['data']['default_expiry_days']);
    }

    public function testCreateProductWithAllFields(): void
    {
        $category = CategoryFactory::createOne();
        $location = LocationFactory::createOne();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId()->toRfc4122(),
            'default_location_id' => $location->getId()->toRfc4122(),
            'default_expiry_days' => 90,
            'min_stock' => 10,
            'active' => false
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('New Product', $data['data']['name']);
        static::assertSame(90, $data['data']['default_expiry_days']);
        static::assertSame(10, $data['data']['min_stock']);
        static::assertFalse($data['data']['active']);
    }

    public function testCreateProductValidationErrorMissingName(): void
    {
        $category = CategoryFactory::createOne();
        $location = LocationFactory::createOne();

        $response = $this->apiPost('/products', [
            'category_id' => $category->getId()->toRfc4122(),
            'default_location_id' => $location->getId()->toRfc4122()
        ]);

        static::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    public function testCreateProductValidationErrorMissingCategoryId(): void
    {
        $location = LocationFactory::createOne();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'default_location_id' => $location->getId()->toRfc4122()
        ]);

        static::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    public function testCreateProductValidationErrorMissingDefaultLocationId(): void
    {
        $category = CategoryFactory::createOne();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId()->toRfc4122()
        ]);

        static::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    public function testCreateProductInvalidCategory(): void
    {
        $location = LocationFactory::createOne();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => Uuid::v7()->toRfc4122(),
            'default_location_id' => $location->getId()->toRfc4122()
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_BAD_REQUEST);

        static::assertSame('Category not found', $data['detail']);
    }

    public function testCreateProductInvalidLocation(): void
    {
        $category = CategoryFactory::createOne();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId()->toRfc4122(),
            'default_location_id' => Uuid::v7()->toRfc4122()
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_BAD_REQUEST);

        static::assertSame('Location not found', $data['detail']);
    }

    public function testCreateProductDuplicateName(): void
    {
        $category = CategoryFactory::createOne();
        $location = LocationFactory::createOne();
        ProductFactory::createOne(['name' => 'Existing Product']);

        $response = $this->apiPost('/products', [
            'name' => 'Existing Product',
            'category_id' => $category->getId()->toRfc4122(),
            'default_location_id' => $location->getId()->toRfc4122()
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testCreateProductInvalidMinStock(): void
    {
        $category = CategoryFactory::createOne();
        $location = LocationFactory::createOne();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId()->toRfc4122(),
            'default_location_id' => $location->getId()->toRfc4122(),
            'min_stock' => -1
        ]);

        static::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    public function testCreateProductInvalidExpiryDays(): void
    {
        $category = CategoryFactory::createOne();
        $location = LocationFactory::createOne();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId()->toRfc4122(),
            'default_location_id' => $location->getId()->toRfc4122(),
            'default_expiry_days' => 0
        ]);

        static::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    // ========== Update Tests ==========

    public function testUpdateProductName(): void
    {
        $product = ProductFactory::createOne(['name' => 'Old Name']);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'name' => 'New Name'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('New Name', $data['data']['name']);
    }

    public function testUpdateProductCategory(): void
    {
        $oldCategory = CategoryFactory::createOne(['name' => 'Old Category']);
        $newCategory = CategoryFactory::createOne(['name' => 'New Category']);
        $product = ProductFactory::createOne(['category' => $oldCategory]);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'category_id' => $newCategory->getId()->toRfc4122()
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('New Category', $data['data']['category']['name']);
    }

    public function testUpdateProductLocation(): void
    {
        $oldLocation = LocationFactory::createOne(['name' => 'Old Location']);
        $newLocation = LocationFactory::createOne(['name' => 'New Location']);
        $product = ProductFactory::createOne(['defaultLocation' => $oldLocation]);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'default_location_id' => $newLocation->getId()->toRfc4122()
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('New Location', $data['data']['default_location']['name']);
    }

    public function testUpdateProductMinStock(): void
    {
        $product = ProductFactory::createOne(['minStock' => 5]);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'min_stock' => 15
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(15, $data['data']['min_stock']);
    }

    public function testUpdateProductExpiryDays(): void
    {
        $product = ProductFactory::createOne(['defaultExpiryDays' => null]);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'default_expiry_days' => 60
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(60, $data['data']['default_expiry_days']);
    }

    public function testUpdateProductClearExpiryDays(): void
    {
        $product = ProductFactory::createOne(['defaultExpiryDays' => 30]);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'clear_default_expiry_days' => true
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertNull($data['data']['default_expiry_days']);
    }

    public function testUpdateProductActive(): void
    {
        $product = ProductFactory::createOne(['active' => true]);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'active' => false
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertFalse($data['data']['active']);
    }

    public function testUpdateProductEmptyPayload(): void
    {
        $product = ProductFactory::createOne(['name' => 'Original Name']);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), []);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('Original Name', $data['data']['name']);
    }

    public function testUpdateProductNotFound(): void
    {
        $response = $this->apiPatch('/products/' . Uuid::v7()->toRfc4122(), [
            'name' => 'New Name'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_NOT_FOUND);

        static::assertArrayHasKey('detail', $data);
    }

    public function testUpdateProductInvalidUuid(): void
    {
        $response = $this->apiPatch('/products/not-a-uuid', [
            'name' => 'New Name'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_BAD_REQUEST);

        static::assertSame('Invalid UUID format', $data['detail']);
    }

    // ========== Delete Tests ==========

    public function testDeleteProductSoftDeleteByDefault(): void
    {
        $product = ProductFactory::createOne(['active' => true]);
        $productId = $product->getId()->toRfc4122();

        $response = $this->apiDelete('/products/' . $productId);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // Verify product is deactivated but still exists
        $response = $this->apiGet('/products/' . $productId);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertFalse($data['data']['active']);
    }

    public function testDeleteProductHardDelete(): void
    {
        $product = ProductFactory::createOne();
        $productId = $product->getId()->toRfc4122();

        $response = $this->apiDelete('/products/' . $productId, ['hard' => '1']);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // Verify product no longer exists
        $response = $this->apiGet('/products/' . $productId);
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testDeleteProductNotFound(): void
    {
        $response = $this->apiDelete('/products/' . Uuid::v7()->toRfc4122());
        $data = static::assertJsonResponse($response, Response::HTTP_NOT_FOUND);

        static::assertArrayHasKey('detail', $data);
    }

    public function testDeleteProductInvalidUuid(): void
    {
        $response = $this->apiDelete('/products/not-a-uuid');
        $data = static::assertJsonResponse($response, Response::HTTP_BAD_REQUEST);

        static::assertSame('Invalid UUID format', $data['detail']);
    }
}
