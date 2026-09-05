<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Enum\ShoppingListSource;
use App\Tests\Factory\LocationFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\RecipeFactory;
use App\Tests\Factory\RecipeIngredientFactory;
use App\Tests\Factory\StockEntryFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RecipeControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loginAs(UserFactory::createOne());
    }

    public function testCreateRecipe(): void
    {
        $pasta = ProductFactory::createOne(['name' => 'Pasta']);
        $sauce = ProductFactory::createOne(['name' => 'Sauce']);

        $response = $this->apiPost('/recipes', [
            'name' => 'Pasta with sauce',
            'instructions' => null,
            'source_url' => null,
            'ingredients' => [
                ['product_id' => (string) $pasta->getId(), 'required_count' => 1, 'consume_on_cook' => false],
                ['product_id' => (string) $sauce->getId(), 'required_count' => 1, 'consume_on_cook' => true]
            ]
        ]);

        $body = static::assertJsonResponse($response, Response::HTTP_CREATED);
        static::assertSame('Pasta with sauce', $body['data']['name']);
        static::assertCount(2, $body['data']['ingredients']);
        static::assertFalse($body['data']['cookable']);
    }

    public function testListComputesFulfillmentAcrossLocations(): void
    {
        $product = ProductFactory::createOne();
        $fridge = LocationFactory::createOne();
        $pantry = LocationFactory::createOne();
        StockEntryFactory::createOne(['product' => $product, 'location' => $fridge]);
        StockEntryFactory::createOne(['product' => $product, 'location' => $pantry]);

        $recipe = RecipeFactory::createOne(['name' => 'R']);
        RecipeIngredientFactory::createOne(['recipe' => $recipe, 'product' => $product, 'requiredCount' => 2]);

        $body = static::assertJsonResponse($this->apiGet('/recipes'), Response::HTTP_OK);
        static::assertListResponse($body, 1);
        $ingredient = $body['data'][0]['ingredients'][0];
        static::assertSame(2, $ingredient['in_stock']);
        static::assertTrue($ingredient['has_enough']);
        static::assertSame(0, $ingredient['shortfall']);
        static::assertTrue($body['data'][0]['cookable']);
    }

    public function testCookConsumesOnlyConsumeOnCookIngredients(): void
    {
        $pasta = ProductFactory::createOne(['name' => 'Pasta']);
        $sauce = ProductFactory::createOne(['name' => 'Sauce']);
        $location = LocationFactory::createOne();
        StockEntryFactory::createOne(['product' => $pasta, 'location' => $location]);
        StockEntryFactory::createOne(['product' => $sauce, 'location' => $location]);

        $recipe = RecipeFactory::createOne();
        RecipeIngredientFactory::createOne([
            'recipe' => $recipe,
            'product' => $pasta,
            'requiredCount' => 1,
            'consumeOnCook' => false
        ]);
        RecipeIngredientFactory::createOne([
            'recipe' => $recipe,
            'product' => $sauce,
            'requiredCount' => 1,
            'consumeOnCook' => true
        ]);

        $response = $this->apiPost('/recipes/' . $recipe->getId() . '/cook', []);
        static::assertJsonResponse($response, Response::HTTP_OK);

        $repo = static::getContainer()->get(\App\Repository\StockEntryRepository::class);
        static::assertSame(1, $repo->countByProduct($pasta->getId()));
        static::assertSame(0, $repo->countByProduct($sauce->getId()));
    }

    public function testCookBelowMinStockAddsAutoItemToShoppingList(): void
    {
        $location = LocationFactory::createOne();
        // minStock=3; cook consumes 2 of 3 → 1 remains → deficit 2
        $milk = ProductFactory::createOne(['name' => 'Milk', 'minStock' => 3]);
        StockEntryFactory::createMany(3, ['product' => $milk, 'location' => $location]);

        $recipe = RecipeFactory::createOne();
        RecipeIngredientFactory::createOne([
            'recipe' => $recipe,
            'product' => $milk,
            'requiredCount' => 2,
            'consumeOnCook' => true
        ]);

        $response = $this->apiPost('/recipes/' . $recipe->getId() . '/cook', []);
        static::assertJsonResponse($response, Response::HTTP_OK);

        // Reconciliation fires after the cook transaction commits: auto item must exist.
        $data = static::assertJsonResponse($this->apiGet('/shopping-list'), Response::HTTP_OK);
        static::assertListResponse($data, 1);
        static::assertSame('auto', $data['data'][0]['source']);
        static::assertSame(2, $data['data'][0]['amount']); // deficit: minStock 3 - remaining 1
    }

    public function testCookBlockedWhenNotCookable(): void
    {
        $product = ProductFactory::createOne();
        $recipe = RecipeFactory::createOne();
        RecipeIngredientFactory::createOne(['recipe' => $recipe, 'product' => $product, 'requiredCount' => 1]);

        $response = $this->apiPost('/recipes/' . $recipe->getId() . '/cook', []);
        $body = static::assertErrorResponse($response, Response::HTTP_CONFLICT);
        static::assertSame('RECIPE_NOT_COOKABLE', $body['type']);
    }

    public function testAddMissingPushesShortfallWithRecipeSource(): void
    {
        $product = ProductFactory::createOne(['name' => 'Sauce', 'minStock' => 0]);
        $recipe = RecipeFactory::createOne();
        RecipeIngredientFactory::createOne(['recipe' => $recipe, 'product' => $product, 'requiredCount' => 3]);

        $response = $this->apiPost('/recipes/' . $recipe->getId() . '/add-missing-to-shopping-list', []);
        static::assertJsonResponse($response, Response::HTTP_OK);

        $repo = static::getContainer()->get(\App\Repository\ShoppingListItemRepository::class);
        $item = $repo->findByProduct($product);
        static::assertNotNull($item);
        static::assertSame(3, $item->getAmount());
        static::assertSame(ShoppingListSource::RECIPE, $item->getSource());
    }

    public function testAddMissingDoesNotDuplicateExistingItem(): void
    {
        $product = ProductFactory::createOne(['name' => 'Sauce', 'minStock' => 0]);
        \App\Tests\Factory\ShoppingListItemFactory::createOne([
            'product' => $product,
            'amount' => 1,
            'source' => ShoppingListSource::MANUAL
        ]);
        $recipe = RecipeFactory::createOne();
        RecipeIngredientFactory::createOne(['recipe' => $recipe, 'product' => $product, 'requiredCount' => 3]);

        $this->apiPost('/recipes/' . $recipe->getId() . '/add-missing-to-shopping-list', []);

        $repo = static::getContainer()->get(\App\Repository\ShoppingListItemRepository::class);
        static::assertCount(1, $repo->findAll());
        $item = $repo->findByProduct($product);
        static::assertSame(ShoppingListSource::MANUAL, $item->getSource());
    }

    public function testShowRecipe(): void
    {
        $recipe = RecipeFactory::createOne(['name' => 'My Soup']);

        $body = static::assertJsonResponse($this->apiGet('/recipes/' . $recipe->getId()), Response::HTTP_OK);

        static::assertSame((string) $recipe->getId(), $body['data']['id']);
        static::assertSame('My Soup', $body['data']['name']);
    }

    public function testUpdateReplacesIngredients(): void
    {
        $productA = ProductFactory::createOne(['name' => 'Keep']);
        $productB = ProductFactory::createOne(['name' => 'Remove']);
        $productC = ProductFactory::createOne(['name' => 'New']);

        $recipe = RecipeFactory::createOne(['name' => 'Original Name']);
        RecipeIngredientFactory::createOne([
            'recipe' => $recipe,
            'product' => $productA,
            'requiredCount' => 1,
            'consumeOnCook' => false
        ]);
        RecipeIngredientFactory::createOne([
            'recipe' => $recipe,
            'product' => $productB,
            'requiredCount' => 1,
            'consumeOnCook' => false
        ]);

        $body = static::assertJsonResponse(
            $this->apiPut('/recipes/' . $recipe->getId(), [
                'name' => 'Updated Name',
                'instructions' => null,
                'source_url' => null,
                'ingredients' => [
                    ['product_id' => (string) $productA->getId(), 'required_count' => 2, 'consume_on_cook' => true],
                    ['product_id' => (string) $productC->getId(), 'required_count' => 1, 'consume_on_cook' => false]
                ]
            ]),
            Response::HTTP_OK
        );

        static::assertSame('Updated Name', $body['data']['name']);
        static::assertCount(2, $body['data']['ingredients']);

        $names = array_column($body['data']['ingredients'], 'product_name');
        static::assertContains('Keep', $names);
        static::assertContains('New', $names);
        static::assertNotContains('Remove', $names);

        $keepIngredient = array_values(array_filter(
            $body['data']['ingredients'],
            static fn(array $i): bool => $i['product_name'] === 'Keep'
        ))[0];
        static::assertSame(2, $keepIngredient['required_count']);
    }

    public function testDeleteRecipe(): void
    {
        $recipe = RecipeFactory::createOne();
        $response = $this->apiDelete('/recipes/' . $recipe->getId());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testCreateRejectsDuplicateIngredientProduct(): void
    {
        $pasta = ProductFactory::createOne(['name' => 'Pasta']);

        $response = $this->apiPost('/recipes', [
            'name' => 'Double pasta',
            'ingredients' => [
                ['product_id' => (string) $pasta->getId(), 'required_count' => 1],
                ['product_id' => (string) $pasta->getId(), 'required_count' => 2]
            ]
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);

        static::assertSame('VALIDATION_ERROR', $data['type']);
        static::assertSame('ingredients', $data['errors'][0]['property']);
    }
}
