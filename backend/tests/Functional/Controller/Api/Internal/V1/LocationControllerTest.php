<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Factory\LocationFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LocationControllerTest extends WebTestCase
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
        $response = $this->apiGet('/locations');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 0);
    }

    public function testListReturnsLocationsOrderedByName(): void
    {
        LocationFactory::createOne(['name' => 'Warehouse']);
        LocationFactory::createOne(['name' => 'Basement']);
        LocationFactory::createOne(['name' => 'Garage']);

        $response = $this->apiGet('/locations');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 3);
        static::assertSame('Basement', $data['data'][0]['name']);
        static::assertSame('Garage', $data['data'][1]['name']);
        static::assertSame('Warehouse', $data['data'][2]['name']);
    }

    public function testListResponseStructure(): void
    {
        LocationFactory::createOne(['name' => 'Test Location']);

        $response = $this->apiGet('/locations');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);

        $location = $data['data'][0];
        static::assertArrayHasKey('id', $location);
        static::assertArrayHasKey('name', $location);
        static::assertSame('Test Location', $location['name']);
        static::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $location['id']
        );
    }
}
