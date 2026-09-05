<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Location;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\StockEntryFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LocationControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loginAs(UserFactory::createOne());
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
        static::assertTrue(Uuid::isValid($location['id']));
    }

    public function testListIncludesUsageCount(): void
    {
        $location = LocationFactory::createOne(['name' => 'Гараж']);
        ProductFactory::createOne(['defaultLocation' => $location]);

        $response = $this->apiGet('/locations');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        $garage = array_values(array_filter($data['data'], static fn($l) => $l['name'] === 'Гараж'))[0];
        static::assertSame(1, $garage['usage_count']);
    }

    public function testCreateLocation(): void
    {
        $response = $this->apiPost('/locations', ['name' => 'Балкон']);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('Балкон', $data['data']['name']);
        static::assertSame(0, $data['data']['usage_count']);
        $this->assertDatabaseHas(Location::class, ['name' => 'Балкон']);
    }

    public function testCreateDuplicateNameConflicts(): void
    {
        LocationFactory::createOne(['name' => 'Балкон']);

        $response = $this->apiPost('/locations', ['name' => 'Балкон']);
        $data = static::assertErrorResponse($response, Response::HTTP_CONFLICT);

        static::assertSame('LOCATION_NAME_TAKEN', $data['type']);
    }

    public function testCreateBlankNameIsUnprocessable(): void
    {
        $response = $this->apiPost('/locations', ['name' => '']);
        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRenameLocation(): void
    {
        $location = LocationFactory::createOne(['name' => 'Балкон']);

        $response = $this->apiPatch('/locations/' . $location->getId(), ['name' => 'Лоджия']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('Лоджия', $data['data']['name']);
        $this->assertDatabaseHas(Location::class, ['name' => 'Лоджия']);
    }

    public function testRenameMissingLocationIsNotFound(): void
    {
        $response = $this->apiPatch('/locations/' . Uuid::v7(), ['name' => 'Лоджия']);
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);
        static::assertSame('LOCATION_NOT_FOUND', $data['type']);
    }

    public function testDeleteEmptyLocation(): void
    {
        $location = LocationFactory::createOne(['name' => 'Балкон']);

        $response = $this->apiDelete('/locations/' . $location->getId());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $this->assertDatabaseMissing(Location::class, ['name' => 'Балкон']);
    }

    public function testDeleteInUseLocationConflicts(): void
    {
        $location = LocationFactory::createOne(['name' => 'Гараж']);
        ProductFactory::createOne(['defaultLocation' => $location]);

        $response = $this->apiDelete('/locations/' . $location->getId());
        $data = static::assertErrorResponse($response, Response::HTTP_CONFLICT);

        static::assertSame('LOCATION_IN_USE', $data['type']);
        static::assertSame(1, $data['usageCount']);
        $this->assertDatabaseHas(Location::class, ['name' => 'Гараж']);
    }

    public function testListUsageCountIncludesStockEntries(): void
    {
        $location = LocationFactory::createOne(['name' => 'Подвал']);
        StockEntryFactory::createOne(['location' => $location]);

        $response = $this->apiGet('/locations');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        $cellar = array_values(array_filter($data['data'], static fn($l) => $l['name'] === 'Подвал'))[0];
        static::assertSame(1, $cellar['usage_count']);
    }

    public function testRenameToExistingNameConflicts(): void
    {
        LocationFactory::createOne(['name' => 'Балкон']);
        $other = LocationFactory::createOne(['name' => 'Гараж']);

        $response = $this->apiPatch('/locations/' . $other->getId(), ['name' => 'Балкон']);
        $data = static::assertErrorResponse($response, Response::HTTP_CONFLICT);

        static::assertSame('LOCATION_NAME_TAKEN', $data['type']);
    }

    public function testDeleteMissingLocationIsNotFound(): void
    {
        $response = $this->apiDelete('/locations/' . Uuid::v7());
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('LOCATION_NOT_FOUND', $data['type']);
    }
}
