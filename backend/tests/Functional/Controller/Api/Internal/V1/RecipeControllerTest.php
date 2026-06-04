<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Enum\ShoppingListSource;
use App\Factory\LocationFactory;
use App\Factory\ProductFactory;
use App\Factory\RecipeFactory;
use App\Factory\RecipeIngredientFactory;
use App\Factory\StockEntryFactory;
use App\Factory\UserFactory;
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
        \App\Factory\ShoppingListItemFactory::createOne([
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

    public function testDeleteRecipe(): void
    {
        $recipe = RecipeFactory::createOne();
        $response = $this->apiDelete('/recipes/' . $recipe->getId());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
