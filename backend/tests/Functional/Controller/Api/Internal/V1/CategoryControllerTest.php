<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Category;
use App\Factory\CategoryFactory;
use App\Factory\ProductFactory;
use App\Factory\UserFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CategoryControllerTest extends WebTestCase
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

    public function testListIncludesUsageCount(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Снеки']);
        ProductFactory::createOne(['category' => $category]);

        $response = $this->apiGet('/categories');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        $snacks = array_values(array_filter($data['data'], fn($c) => $c['name'] === 'Снеки'))[0];
        static::assertSame(1, $snacks['usage_count']);
    }

    public function testCreateCategory(): void
    {
        $response = $this->apiPost('/categories', ['name' => 'Снеки']);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('Снеки', $data['data']['name']);
        static::assertSame(0, $data['data']['usage_count']);
        $this->assertDatabaseHas(Category::class, ['name' => 'Снеки']);
    }

    public function testCreateDuplicateNameConflicts(): void
    {
        CategoryFactory::createOne(['name' => 'Снеки']);

        $response = $this->apiPost('/categories', ['name' => 'Снеки']);
        $data = static::assertErrorResponse($response, Response::HTTP_CONFLICT);
        static::assertSame('CATEGORY_NAME_TAKEN', $data['type']);
    }

    public function testCreateBlankNameIsUnprocessable(): void
    {
        $response = $this->apiPost('/categories', ['name' => '']);
        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRenameCategory(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Снеки']);

        $response = $this->apiPatch('/categories/' . $category->getId(), ['name' => 'Закуски']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertSame('Закуски', $data['data']['name']);
        $this->assertDatabaseHas(Category::class, ['name' => 'Закуски']);
    }

    public function testRenameMissingCategoryIsNotFound(): void
    {
        $response = $this->apiPatch('/categories/' . Uuid::v7(), ['name' => 'Закуски']);
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);
        static::assertSame('CATEGORY_NOT_FOUND', $data['type']);
    }

    public function testRenameToExistingNameConflicts(): void
    {
        CategoryFactory::createOne(['name' => 'Снеки']);
        $other = CategoryFactory::createOne(['name' => 'Напитки2']);

        $response = $this->apiPatch('/categories/' . $other->getId(), ['name' => 'Снеки']);
        $data = static::assertErrorResponse($response, Response::HTTP_CONFLICT);
        static::assertSame('CATEGORY_NAME_TAKEN', $data['type']);
    }

    public function testDeleteEmptyCategory(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Снеки']);

        $response = $this->apiDelete('/categories/' . $category->getId());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $this->assertDatabaseMissing(Category::class, ['name' => 'Снеки']);
    }

    public function testDeleteInUseCategoryConflicts(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Снеки']);
        ProductFactory::createOne(['category' => $category]);

        $response = $this->apiDelete('/categories/' . $category->getId());
        $data = static::assertErrorResponse($response, Response::HTTP_CONFLICT);
        static::assertSame('CATEGORY_IN_USE', $data['type']);
        static::assertSame(1, $data['usageCount']);
        $this->assertDatabaseHas(Category::class, ['name' => 'Снеки']);
    }

    public function testDeleteMissingCategoryIsNotFound(): void
    {
        $response = $this->apiDelete('/categories/' . Uuid::v7());
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);
        static::assertSame('CATEGORY_NOT_FOUND', $data['type']);
    }
}
