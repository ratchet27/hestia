<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Factory\CategoryFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CategoryControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testListReturnsEmptyWhenNoData(): void
    {
        $response = $this->apiGet('/categories');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 0);
    }

    public function testListReturnsCategoriesOrderedByName(): void
    {
        CategoryFactory::createOne(['name' => 'Zebra']);
        CategoryFactory::createOne(['name' => 'Apple']);
        CategoryFactory::createOne(['name' => 'Mango']);

        $response = $this->apiGet('/categories');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 3);
        static::assertSame('Apple', $data['data'][0]['name']);
        static::assertSame('Mango', $data['data'][1]['name']);
        static::assertSame('Zebra', $data['data'][2]['name']);
    }

    public function testListResponseStructure(): void
    {
        CategoryFactory::createOne(['name' => 'Test Category']);

        $response = $this->apiGet('/categories');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);

        $category = $data['data'][0];
        static::assertArrayHasKey('id', $category);
        static::assertArrayHasKey('name', $category);
        static::assertSame('Test Category', $category['name']);
        static::assertTrue(Uuid::isValid($category['id']));
    }
}
