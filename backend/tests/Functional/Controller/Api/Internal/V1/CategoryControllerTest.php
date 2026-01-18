<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Factory\CategoryFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CategoryControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testListReturnsEmptyWhenNoData(): void
    {
        $response = $this->apiGet('/categories');
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 0);
    }

    public function testListReturnsCategoriesOrderedByName(): void
    {
        CategoryFactory::createOne(['name' => 'Zebra']);
        CategoryFactory::createOne(['name' => 'Apple']);
        CategoryFactory::createOne(['name' => 'Mango']);

        $response = $this->apiGet('/categories');
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 3);
        $this->assertSame('Apple', $data['data'][0]['name']);
        $this->assertSame('Mango', $data['data'][1]['name']);
        $this->assertSame('Zebra', $data['data'][2]['name']);
    }

    public function testListResponseStructure(): void
    {
        CategoryFactory::createOne(['name' => 'Test Category']);

        $response = $this->apiGet('/categories');
        $data = $this->assertJsonResponse($response, Response::HTTP_OK);

        $this->assertListResponse($data, 1);

        $category = $data['data'][0];
        $this->assertArrayHasKey('id', $category);
        $this->assertArrayHasKey('name', $category);
        $this->assertSame('Test Category', $category['name']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $category['id']
        );
    }
}
