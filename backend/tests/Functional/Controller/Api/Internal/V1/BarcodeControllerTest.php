<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Barcode;
use App\Entity\Product;
use App\Factory\BarcodeFactory;
use App\Factory\ProductFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

// @mago-ignore lint:too-many-methods
class BarcodeControllerTest extends WebTestCase
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

    // ========== List Tests ==========

    public function testListBarcodesReturnsEmptyWhenNoData(): void
    {
        $product = $this->createProduct();
        $response = $this->apiGet('/products/' . $product->getId() . '/barcodes');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertListResponse($data, 0);
    }

    public function testListBarcodesReturnsBarcodesForProduct(): void
    {
        $product = $this->createProduct();
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product]);
        BarcodeFactory::createOne(['barcode' => '9876543210987', 'product' => $product]);

        $response = $this->apiGet('/products/' . $product->getId() . '/barcodes');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListBarcodesDoesNotIncludeOtherProductBarcodes(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product1]);
        BarcodeFactory::createOne(['barcode' => '9876543210987', 'product' => $product2]);

        $response = $this->apiGet('/products/' . $product1->getId() . '/barcodes');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame('1234567890123', $data['data'][0]['barcode']);
    }

    public function testListBarcodesProductNotFound(): void
    {
        $response = $this->apiGet('/products/' . Uuid::v7() . '/barcodes');
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('Product not found', $data['title']);
        static::assertSame('PRODUCT_NOT_FOUND', $data['type']);
    }

    public function testListBarcodesNotFoundForInvalidUuid(): void
    {
        $response = $this->apiGet('/products/not-a-uuid/barcodes');

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    // ========== Add Tests ==========

    public function testAddBarcodeSuccess(): void
    {
        $product = $this->createProduct();

        $response = $this->apiPost('/products/' . $product->getId() . '/barcodes', [
            'barcode' => '1234567890123'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertArrayHasKey('data', $data);
        static::assertSame('1234567890123', $data['data']['barcode']);

        $this->assertDatabaseHas(Barcode::class, ['barcode' => '1234567890123']);
    }

    public function testAddBarcodeResponseStructure(): void
    {
        $product = $this->createProduct();

        $response = $this->apiPost('/products/' . $product->getId() . '/barcodes', [
            'barcode' => '1234567890123'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        $barcodeData = $data['data'];
        static::assertArrayHasKey('id', $barcodeData);
        static::assertArrayHasKey('barcode', $barcodeData);
        static::assertTrue(Uuid::isValid($barcodeData['id']));
    }

    public function testAddBarcodeDuplicateCode(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product1]);

        $response = $this->apiPost('/products/' . $product2->getId() . '/barcodes', [
            'barcode' => '1234567890123'
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_CONFLICT);

        static::assertSame('Barcode already exists', $data['title']);
        static::assertSame('BARCODE_ALREADY_EXISTS', $data['type']);
    }

    public function testAddBarcodeEmptyCode(): void
    {
        $product = $this->createProduct();

        $response = $this->apiPost('/products/' . $product->getId() . '/barcodes', [
            'barcode' => ''
        ]);

        static::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    public function testAddBarcodeTooLongCode(): void
    {
        $product = $this->createProduct();

        $response = $this->apiPost('/products/' . $product->getId() . '/barcodes', [
            'barcode' => str_repeat('1', 51)
        ]);

        static::assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    public function testAddBarcodeProductNotFound(): void
    {
        $response = $this->apiPost('/products/' . Uuid::v7() . '/barcodes', [
            'barcode' => '1234567890123'
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('Product not found', $data['title']);
        static::assertSame('PRODUCT_NOT_FOUND', $data['type']);
    }

    // ========== Remove Tests ==========

    public function testRemoveBarcodeSuccess(): void
    {
        $product = $this->createProduct();
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product]);

        $response = $this->apiDelete('/barcodes/1234567890123');

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // Verify barcode is removed
        $response = $this->apiGet('/products/' . $product->getId() . '/barcodes');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 0);
    }

    public function testRemoveBarcodeBarcodeNotFound(): void
    {
        $response = $this->apiDelete('/barcodes/nonexistent');
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('Barcode not found', $data['title']);
        static::assertSame('BARCODE_NOT_FOUND', $data['type']);
    }

    // ========== Lookup Tests ==========

    public function testLookupBarcodeReturnsProduct(): void
    {
        $product = $this->createProduct(['name' => 'Test Product']);
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product]);

        $response = $this->apiGet('/barcodes/1234567890123');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertArrayHasKey('data', $data);
        static::assertSame('Test Product', $data['data']['name']);
        static::assertSame($product->getId()->toString(), $data['data']['id']);
    }

    public function testLookupBarcodeNotFound(): void
    {
        $response = $this->apiGet('/barcodes/nonexistent');
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('Barcode not found', $data['title']);
        static::assertSame('BARCODE_NOT_FOUND', $data['type']);
    }
}
