<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Category;
use App\Entity\Location;
use App\Entity\Product;
use App\Factory\BarcodeFactory;
use App\Factory\CategoryFactory;
use App\Factory\LocationFactory;
use App\Factory\ProductFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
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

    protected function setUp(): void
    {
        $this->client = static::createClient();
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
        $this->createProduct(['name' => 'Apple Juice']);
        $this->createProduct(['name' => 'Orange Juice']);
        $this->createProduct(['name' => 'Milk']);

        $response = $this->apiGet('/products', ['name' => 'Juice']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListFilterByCategoryId(): void
    {
        $category1 = $this->createCategory(['name' => 'Beverages']);
        $category2 = $this->createCategory(['name' => 'Dairy']);

        $this->createProduct(['category' => $category1]);
        $this->createProduct(['category' => $category1]);
        $this->createProduct(['category' => $category2]);

        $response = $this->apiGet('/products', ['category_id' => $category1->getId()]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListFilterByActive(): void
    {
        $this->createProduct(['active' => true]);
        $this->createProduct(['active' => true]);
        $this->createProduct(['active' => false]);

        $response = $this->apiGet('/products', ['active' => '1']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListFilterByInactive(): void
    {
        $this->createProduct(['active' => true]);
        $this->createProduct(['active' => false]);

        $response = $this->apiGet('/products', ['active' => '0']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
    }

    public function testListCombinedFilters(): void
    {
        $category = $this->createCategory(['name' => 'Beverages']);

        $this->createProduct(['name' => 'Apple Juice', 'category' => $category, 'active' => true]);
        $this->createProduct(['name' => 'Orange Juice', 'category' => $category, 'active' => false]);
        $this->createProduct(['name' => 'Apple Cider', 'active' => true]);

        $response = $this->apiGet('/products', [
            'name' => 'Apple',
            'category_id' => $category->getId(),
            'active' => '1'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame('Apple Juice', $data['data'][0]['name']);
    }

    // ========== Show Tests ==========

    public function testShowReturnsProduct(): void
    {
        $product = $this->createProduct(['name' => 'Test Product']);

        $response = $this->apiGet('/products/' . $product->getId());
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertArrayHasKey('data', $data);
        static::assertSame('Test Product', $data['data']['name']);
    }

    public function testShowReturnsNotFoundForMissingProduct(): void
    {
        $response = $this->apiGet('/products/' . Uuid::v7());
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('Product not found', $data['title']);
        static::assertSame('PRODUCT_NOT_FOUND', $data['type']);
    }

    public function testShowReturnsNotFoundForInvalidUuid(): void
    {
        $response = $this->apiGet('/products/not-a-uuid');

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testShowIncludesBarcodes(): void
    {
        $product = $this->createProduct(['name' => 'Test Product']);
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product]);
        BarcodeFactory::createOne(['barcode' => '9876543210987', 'product' => $product]);

        $response = $this->apiGet('/products/' . $product->getId());
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertArrayHasKey('barcodes', $data['data']);
        static::assertCount(2, $data['data']['barcodes']);
    }

    public function testShowResponseStructure(): void
    {
        $category = $this->createCategory(['name' => 'Test Category']);
        $location = $this->createLocation(['name' => 'Test Location']);
        $product = $this->createProduct([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
            'defaultExpiryDays' => 30,
            'minStock' => 5,
            'active' => true
        ]);

        $response = $this->apiGet('/products/' . $product->getId());
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
        $category = $this->createCategory();
        $location = $this->createLocation();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId(),
            'default_location_id' => $location->getId()
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
        $category = $this->createCategory();
        $location = $this->createLocation();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId(),
            'default_location_id' => $location->getId(),
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
        $category = $this->createCategory();
        $location = $this->createLocation();

        $response = $this->apiPost('/products', [
            'category_id' => $category->getId(),
            'default_location_id' => $location->getId()
        ]);

        static::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    public function testCreateProductValidationErrorMissingCategoryId(): void
    {
        $location = $this->createLocation();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'default_location_id' => $location->getId()
        ]);

        static::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    public function testCreateProductValidationErrorMissingDefaultLocationId(): void
    {
        $category = $this->createCategory();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId()
        ]);

        static::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    public function testCreateProductInvalidCategory(): void
    {
        $location = $this->createLocation();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => Uuid::v7(),
            'default_location_id' => $location->getId()
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_BAD_REQUEST);

        static::assertSame('Category not found', $data['title']);
        static::assertSame('CATEGORY_NOT_FOUND', $data['type']);
    }

    public function testCreateProductInvalidLocation(): void
    {
        $category = $this->createCategory();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId(),
            'default_location_id' => Uuid::v7()
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_BAD_REQUEST);

        static::assertSame('Location not found', $data['title']);
        static::assertSame('LOCATION_NOT_FOUND', $data['type']);
    }

    public function testCreateProductDuplicateName(): void
    {
        $category = $this->createCategory();
        $location = $this->createLocation();
        $this->createProduct(['name' => 'Existing Product']);

        $response = $this->apiPost('/products', [
            'name' => 'Existing Product',
            'category_id' => $category->getId(),
            'default_location_id' => $location->getId()
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);

        static::assertSame('Validation failed', $data['title']);
        static::assertSame('VALIDATION_ERROR', $data['type']);
    }

    public function testCreateProductInvalidMinStock(): void
    {
        $category = $this->createCategory();
        $location = $this->createLocation();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId(),
            'default_location_id' => $location->getId(),
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
        $category = $this->createCategory();
        $location = $this->createLocation();

        $response = $this->apiPost('/products', [
            'name' => 'New Product',
            'category_id' => $category->getId(),
            'default_location_id' => $location->getId(),
            'default_expiry_days' => 0
        ]);

        static::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    // ========== Update Tests ==========

    /**
     * Build a full PUT payload from a product with optional overrides.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function buildUpdatePayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'name' => $product->getName(),
            'category_id' => (string) $product->getCategory()->getId(),
            'default_location_id' => (string) $product->getDefaultLocation()->getId(),
            'default_expiry_days' => $product->getDefaultExpiryDays(),
            'min_stock' => $product->getMinStock(),
            'active' => $product->isActive()
        ], $overrides);
    }

    public function testUpdateProductName(): void
    {
        $product = $this->createProduct(['name' => 'Old Name']);

        $response = $this->apiPut('/products/' . $product->getId(), $this->buildUpdatePayload($product, [
            'name' => 'New Name'
        ]));
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('New Name', $data['data']['name']);
    }

    public function testUpdateProductCategory(): void
    {
        $oldCategory = $this->createCategory(['name' => 'Old Category']);
        $newCategory = $this->createCategory(['name' => 'New Category']);
        $product = $this->createProduct(['category' => $oldCategory]);

        $response = $this->apiPut('/products/'
            . $product->getId(), $this->buildUpdatePayload($product, ['category_id' =>
            (string) $newCategory->getId()]));
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('New Category', $data['data']['category']['name']);
    }

    public function testUpdateProductLocation(): void
    {
        $oldLocation = $this->createLocation(['name' => 'Old Location']);
        $newLocation = $this->createLocation(['name' => 'New Location']);
        $product = $this->createProduct(['defaultLocation' => $oldLocation]);

        $response = $this->apiPut('/products/'
            . $product->getId(), $this->buildUpdatePayload($product, ['default_location_id' =>
            (string) $newLocation->getId()]));
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('New Location', $data['data']['default_location']['name']);
    }

    public function testUpdateProductMinStock(): void
    {
        $product = $this->createProduct(['minStock' => 5]);

        $response = $this->apiPut('/products/' . $product->getId(), $this->buildUpdatePayload($product, [
            'min_stock' => 15
        ]));
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(15, $data['data']['min_stock']);
    }

    public function testUpdateProductExpiryDays(): void
    {
        $product = $this->createProduct(['defaultExpiryDays' => null]);

        $response = $this->apiPut('/products/' . $product->getId(), $this->buildUpdatePayload($product, [
            'default_expiry_days' => 60
        ]));
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(60, $data['data']['default_expiry_days']);
    }

    public function testUpdateProductClearExpiryDays(): void
    {
        $product = $this->createProduct(['defaultExpiryDays' => 30]);

        $response = $this->apiPut('/products/' . $product->getId(), $this->buildUpdatePayload($product, [
            'default_expiry_days' => null
        ]));
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertNull($data['data']['default_expiry_days']);
    }

    public function testUpdateProductActive(): void
    {
        $product = $this->createProduct(['active' => true]);

        $response = $this->apiPut('/products/' . $product->getId(), $this->buildUpdatePayload($product, [
            'active' => false
        ]));
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertFalse($data['data']['active']);
    }

    public function testUpdateProductNotFound(): void
    {
        $category = $this->createCategory();
        $location = $this->createLocation();

        $response = $this->apiPut('/products/' . Uuid::v7(), [
            'name' => 'New Name',
            'category_id' => (string) $category->getId(),
            'default_location_id' => (string) $location->getId(),
            'min_stock' => 0,
            'active' => true
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('Product not found', $data['title']);
        static::assertSame('PRODUCT_NOT_FOUND', $data['type']);
    }

    public function testUpdateProductNotFoundForInvalidUuid(): void
    {
        $category = $this->createCategory();
        $location = $this->createLocation();

        $response = $this->apiPut('/products/not-a-uuid', [
            'name' => 'New Name',
            'category_id' => (string) $category->getId(),
            'default_location_id' => (string) $location->getId(),
            'min_stock' => 0,
            'active' => true
        ]);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    // ========== Delete Tests ==========

    public function testDeleteProductSoftDeleteByDefault(): void
    {
        $product = $this->createProduct(['active' => true]);
        $productId = $product->getId();

        $response = $this->apiDelete('/products/' . $productId);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // Verify product is deactivated but still exists
        $response = $this->apiGet('/products/' . $productId);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertFalse($data['data']['active']);
    }

    public function testDeleteProductHardDelete(): void
    {
        $product = $this->createProduct();
        $productId = $product->getId();

        $response = $this->apiDelete('/products/' . $productId, ['hard' => '1']);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // Verify product no longer exists
        $response = $this->apiGet('/products/' . $productId);
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testDeleteProductNotFound(): void
    {
        $response = $this->apiDelete('/products/' . Uuid::v7());
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('Product not found', $data['title']);
        static::assertSame('PRODUCT_NOT_FOUND', $data['type']);
    }

    public function testDeleteProductNotFoundForInvalidUuid(): void
    {
        $response = $this->apiDelete('/products/not-a-uuid');

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
