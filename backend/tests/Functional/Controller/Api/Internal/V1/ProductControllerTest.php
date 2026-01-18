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
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 0);
    }

    public function testListReturnsProducts(): void
    {
        ProductFactory::createMany(3);

        $response = $this->apiGet('/products');
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 3);
    }

    public function testListFilterByName(): void
    {
        ProductFactory::createOne(['name' => 'Apple Juice']);
        ProductFactory::createOne(['name' => 'Orange Juice']);
        ProductFactory::createOne(['name' => 'Milk']);

        $response = $this->apiGet('/products', ['name' => 'Juice']);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 2);
    }

    public function testListFilterByCategoryId(): void
    {
        $category1 = CategoryFactory::createOne(['name' => 'Beverages']);
        $category2 = CategoryFactory::createOne(['name' => 'Dairy']);

        ProductFactory::createOne(['category' => $category1]);
        ProductFactory::createOne(['category' => $category1]);
        ProductFactory::createOne(['category' => $category2]);

        $response = $this->apiGet('/products', ['category_id' => $category1->getId()->toRfc4122()]);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 2);
    }

    public function testListFilterByActive(): void
    {
        ProductFactory::createOne(['active' => true]);
        ProductFactory::createOne(['active' => true]);
        ProductFactory::createOne(['active' => false]);

        $response = $this->apiGet('/products', ['active' => '1']);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 2);
    }

    public function testListFilterByInactive(): void
    {
        ProductFactory::createOne(['active' => true]);
        ProductFactory::createOne(['active' => false]);

        $response = $this->apiGet('/products', ['active' => '0']);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 1);
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
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 1);
        $this->assertSame('Apple Juice', $data['data'][0]['name']);
    }

    // ========== Show Tests ==========

    public function testShowReturnsProduct(): void
    {
        $product = ProductFactory::createOne(['name' => 'Test Product']);

        $response = $this->apiGet('/products/' . $product->getId()->toRfc4122());
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertArrayHasKey('data', $data);
        $this->assertSame('Test Product', $data['data']['name']);
    }

    public function testShowReturnsNotFoundForMissingProduct(): void
    {
        $response = $this->apiGet('/products/' . Uuid::v7()->toRfc4122());
        $data = $this->assertJsonResponse($response, Response::HTTP_NOT_FOUND);

        $this->assertArrayHasKey('detail', $data);
    }

    public function testShowReturnsBadRequestForInvalidUuid(): void
    {
        $response = $this->apiGet('/products/not-a-uuid');
        $data = $this->assertJsonResponse($response, Response::HTTP_BAD_REQUEST);

        $this->assertArrayHasKey('detail', $data);
        $this->assertSame('Invalid UUID format', $data['detail']);
    }

    public function testShowIncludesBarcodes(): void
    {
        $product = ProductFactory::createOne(['name' => 'Test Product']);
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product]);
        BarcodeFactory::createOne(['barcode' => '9876543210987', 'product' => $product]);

        $response = $this->apiGet('/products/' . $product->getId()->toRfc4122());
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertArrayHasKey('barcodes', $data['data']);
        $this->assertCount(2, $data['data']['barcodes']);
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
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $productData = $data['data'];
        $this->assertArrayHasKey('id', $productData);
        $this->assertArrayHasKey('name', $productData);
        $this->assertArrayHasKey('category', $productData);
        $this->assertArrayHasKey('default_location', $productData);
        $this->assertArrayHasKey('default_expiry_days', $productData);
        $this->assertArrayHasKey('min_stock', $productData);
        $this->assertArrayHasKey('active', $productData);
        $this->assertArrayHasKey('created_at', $productData);
        $this->assertArrayHasKey('updated_at', $productData);
        $this->assertArrayHasKey('barcodes', $productData);

        $this->assertSame('Test Product', $productData['name']);
        $this->assertSame('Test Category', $productData['category']['name']);
        $this->assertSame('Test Location', $productData['default_location']['name']);
        $this->assertSame(30, $productData['default_expiry_days']);
        $this->assertSame(5, $productData['min_stock']);
        $this->assertTrue($productData['active']);
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
        $data = $this->assertJsonResponse($response, Response::HTTP_CREATED);

        $this->assertArrayHasKey('data', $data);
        $this->assertSame('New Product', $data['data']['name']);
        $this->assertSame(0, $data['data']['min_stock']);
        $this->assertTrue($data['data']['active']);
        $this->assertNull($data['data']['default_expiry_days']);
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
        $data = $this->assertJsonResponse($response, Response::HTTP_CREATED);

        $this->assertSame('New Product', $data['data']['name']);
        $this->assertSame(90, $data['data']['default_expiry_days']);
        $this->assertSame(10, $data['data']['min_stock']);
        $this->assertFalse($data['data']['active']);
    }

    public function testCreateProductValidationErrorMissingName(): void
    {
        $category = CategoryFactory::createOne();
        $location = LocationFactory::createOne();

        $response = $this->apiPost('/products', [
            'category_id' => $category->getId()->toRfc4122(),
            'default_location_id' => $location->getId()->toRfc4122()
        ]);

        $this->assertSame(
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

        $this->assertSame(
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

        $this->assertSame(
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
        $data = $this->assertJsonResponse($response, Response::HTTP_BAD_REQUEST);

        $this->assertSame('Category not found', $data['detail']);
    }

    public function testCreateProductInvalidLocation(): void
    {
        $category = CategoryFactory::createOne();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId()->toRfc4122(),
            'default_location_id' => Uuid::v7()->toRfc4122()
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_BAD_REQUEST);

        $this->assertSame('Location not found', $data['detail']);
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

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
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

        $this->assertSame(
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

        $this->assertSame(
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
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertSame('New Name', $data['data']['name']);
    }

    public function testUpdateProductCategory(): void
    {
        $oldCategory = CategoryFactory::createOne(['name' => 'Old Category']);
        $newCategory = CategoryFactory::createOne(['name' => 'New Category']);
        $product = ProductFactory::createOne(['category' => $oldCategory]);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'category_id' => $newCategory->getId()->toRfc4122()
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertSame('New Category', $data['data']['category']['name']);
    }

    public function testUpdateProductLocation(): void
    {
        $oldLocation = LocationFactory::createOne(['name' => 'Old Location']);
        $newLocation = LocationFactory::createOne(['name' => 'New Location']);
        $product = ProductFactory::createOne(['defaultLocation' => $oldLocation]);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'default_location_id' => $newLocation->getId()->toRfc4122()
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertSame('New Location', $data['data']['default_location']['name']);
    }

    public function testUpdateProductMinStock(): void
    {
        $product = ProductFactory::createOne(['minStock' => 5]);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'min_stock' => 15
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertSame(15, $data['data']['min_stock']);
    }

    public function testUpdateProductExpiryDays(): void
    {
        $product = ProductFactory::createOne(['defaultExpiryDays' => null]);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'default_expiry_days' => 60
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertSame(60, $data['data']['default_expiry_days']);
    }

    public function testUpdateProductClearExpiryDays(): void
    {
        $product = ProductFactory::createOne(['defaultExpiryDays' => 30]);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'clear_default_expiry_days' => true
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertNull($data['data']['default_expiry_days']);
    }

    public function testUpdateProductActive(): void
    {
        $product = ProductFactory::createOne(['active' => true]);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), [
            'active' => false
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertFalse($data['data']['active']);
    }

    public function testUpdateProductEmptyPayload(): void
    {
        $product = ProductFactory::createOne(['name' => 'Original Name']);

        $response = $this->apiPatch('/products/' . $product->getId()->toRfc4122(), []);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertSame('Original Name', $data['data']['name']);
    }

    public function testUpdateProductNotFound(): void
    {
        $response = $this->apiPatch('/products/' . Uuid::v7()->toRfc4122(), [
            'name' => 'New Name'
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_NOT_FOUND);

        $this->assertArrayHasKey('detail', $data);
    }

    public function testUpdateProductInvalidUuid(): void
    {
        $response = $this->apiPatch('/products/not-a-uuid', [
            'name' => 'New Name'
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_BAD_REQUEST);

        $this->assertSame('Invalid UUID format', $data['detail']);
    }

    // ========== Delete Tests ==========

    public function testDeleteProductSoftDeleteByDefault(): void
    {
        $product = ProductFactory::createOne(['active' => true]);
        $productId = $product->getId()->toRfc4122();

        $response = $this->apiDelete('/products/' . $productId);

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // Verify product is deactivated but still exists
        $response = $this->apiGet('/products/' . $productId);
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertFalse($data['data']['active']);
    }

    public function testDeleteProductHardDelete(): void
    {
        $product = ProductFactory::createOne();
        $productId = $product->getId()->toRfc4122();

        $response = $this->apiDelete('/products/' . $productId, ['hard' => '1']);

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // Verify product no longer exists
        $response = $this->apiGet('/products/' . $productId);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testDeleteProductNotFound(): void
    {
        $response = $this->apiDelete('/products/' . Uuid::v7()->toRfc4122());
        $data = $this->assertJsonResponse($response, Response::HTTP_NOT_FOUND);

        $this->assertArrayHasKey('detail', $data);
    }

    public function testDeleteProductInvalidUuid(): void
    {
        $response = $this->apiDelete('/products/not-a-uuid');
        $data = $this->assertJsonResponse($response, Response::HTTP_BAD_REQUEST);

        $this->assertSame('Invalid UUID format', $data['detail']);
    }
}
