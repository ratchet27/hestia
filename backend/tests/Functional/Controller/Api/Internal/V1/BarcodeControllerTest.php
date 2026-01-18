<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Factory\BarcodeFactory;
use App\Factory\ProductFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
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

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    // ========== List Tests ==========

    public function testListBarcodesReturnsEmptyWhenNoData(): void
    {
        $product = ProductFactory::createOne();

        $response = $this->apiGet('/products/' . $product->getId()->toRfc4122() . '/barcodes');
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 0);
    }

    public function testListBarcodesReturnsBarcodesForProduct(): void
    {
        $product = ProductFactory::createOne();
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product]);
        BarcodeFactory::createOne(['barcode' => '9876543210987', 'product' => $product]);

        $response = $this->apiGet('/products/' . $product->getId()->toRfc4122() . '/barcodes');
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 2);
    }

    public function testListBarcodesDoesNotIncludeOtherProductBarcodes(): void
    {
        $product1 = ProductFactory::createOne();
        $product2 = ProductFactory::createOne();
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product1]);
        BarcodeFactory::createOne(['barcode' => '9876543210987', 'product' => $product2]);

        $response = $this->apiGet('/products/' . $product1->getId()->toRfc4122() . '/barcodes');
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 1);
        $this->assertSame('1234567890123', $data['data'][0]['barcode']);
    }

    public function testListBarcodesProductNotFound(): void
    {
        $response = $this->apiGet('/products/' . Uuid::v7()->toRfc4122() . '/barcodes');
        $data = $this->assertJsonResponse($response, Response::HTTP_NOT_FOUND);

        $this->assertArrayHasKey('detail', $data);
    }

    public function testListBarcodesInvalidUuid(): void
    {
        $response = $this->apiGet('/products/not-a-uuid/barcodes');
        $data = $this->assertJsonResponse($response, Response::HTTP_BAD_REQUEST);

        $this->assertSame('Invalid UUID format', $data['detail']);
    }

    // ========== Add Tests ==========

    public function testAddBarcodeSuccess(): void
    {
        $product = ProductFactory::createOne();

        $response = $this->apiPost('/products/' . $product->getId()->toRfc4122() . '/barcodes', [
            'barcode' => '1234567890123'
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_CREATED);

        $this->assertArrayHasKey('data', $data);
        $this->assertSame('1234567890123', $data['data']['barcode']);
    }

    public function testAddBarcodeResponseStructure(): void
    {
        $product = ProductFactory::createOne();

        $response = $this->apiPost('/products/' . $product->getId()->toRfc4122() . '/barcodes', [
            'barcode' => '1234567890123'
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_CREATED);

        $barcodeData = $data['data'];
        $this->assertArrayHasKey('id', $barcodeData);
        $this->assertArrayHasKey('barcode', $barcodeData);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $barcodeData['id']
        );
    }

    public function testAddBarcodeDuplicateCode(): void
    {
        $product1 = ProductFactory::createOne();
        $product2 = ProductFactory::createOne();
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product1]);

        $response = $this->apiPost('/products/' . $product2->getId()->toRfc4122() . '/barcodes', [
            'barcode' => '1234567890123'
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_BAD_REQUEST);

        $this->assertSame('This barcode is already registered', $data['detail']);
    }

    public function testAddBarcodeEmptyCode(): void
    {
        $product = ProductFactory::createOne();

        $response = $this->apiPost('/products/' . $product->getId()->toRfc4122() . '/barcodes', [
            'barcode' => ''
        ]);

        $this->assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    public function testAddBarcodeTooLongCode(): void
    {
        $product = ProductFactory::createOne();

        $response = $this->apiPost('/products/' . $product->getId()->toRfc4122() . '/barcodes', [
            'barcode' => str_repeat('1', 51)
        ]);

        $this->assertSame(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            (string) $response->getContent()
        );
    }

    public function testAddBarcodeProductNotFound(): void
    {
        $response = $this->apiPost('/products/' . Uuid::v7()->toRfc4122() . '/barcodes', [
            'barcode' => '1234567890123'
        ]);
        $data = $this->assertJsonResponse($response, Response::HTTP_NOT_FOUND);

        $this->assertArrayHasKey('detail', $data);
    }

    // ========== Remove Tests ==========

    public function testRemoveBarcodeSuccess(): void
    {
        $product = ProductFactory::createOne();
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product]);

        $response = $this->apiDelete('/products/' . $product->getId()->toRfc4122() . '/barcodes/1234567890123');

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // Verify barcode is removed
        $response = $this->apiGet('/products/' . $product->getId()->toRfc4122() . '/barcodes');
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 0);
    }

    public function testRemoveBarcodeBarcodeNotFound(): void
    {
        $product = ProductFactory::createOne();

        $response = $this->apiDelete('/products/' . $product->getId()->toRfc4122() . '/barcodes/nonexistent');
        $data = $this->assertJsonResponse($response, Response::HTTP_NOT_FOUND);

        $this->assertSame('Barcode not found', $data['detail']);
    }

    public function testRemoveBarcodeWrongProduct(): void
    {
        $product1 = ProductFactory::createOne();
        $product2 = ProductFactory::createOne();
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product1]);

        $response = $this->apiDelete('/products/' . $product2->getId()->toRfc4122() . '/barcodes/1234567890123');
        $data = $this->assertJsonResponse($response, Response::HTTP_NOT_FOUND);

        $this->assertSame('Barcode not found', $data['detail']);
    }

    // ========== Lookup Tests ==========

    public function testLookupBarcodeReturnsProduct(): void
    {
        $product = ProductFactory::createOne(['name' => 'Test Product']);
        BarcodeFactory::createOne(['barcode' => '1234567890123', 'product' => $product]);

        $response = $this->apiGet('/barcodes/1234567890123');
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertArrayHasKey('data', $data);
        $this->assertSame('Test Product', $data['data']['name']);
        $this->assertSame($product->getId()->toRfc4122(), $data['data']['id']);
    }

    public function testLookupBarcodeNotFound(): void
    {
        $response = $this->apiGet('/barcodes/nonexistent');
        $data = $this->assertJsonResponse($response, Response::HTTP_NOT_FOUND);

        $this->assertSame('Barcode not found', $data['detail']);
    }
}
