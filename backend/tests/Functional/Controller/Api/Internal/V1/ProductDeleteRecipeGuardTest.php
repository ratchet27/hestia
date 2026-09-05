<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Product;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\RecipeFactory;
use App\Tests\Factory\RecipeIngredientFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ProductDeleteRecipeGuardTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loginAs(UserFactory::createOne());
    }

    public function testHardDeleteBlockedWhenProductUsedByRecipe(): void
    {
        $product = ProductFactory::createOne();
        $recipe = RecipeFactory::createOne();
        RecipeIngredientFactory::createOne(['recipe' => $recipe, 'product' => $product]);

        $response = $this->apiDelete('/products/' . $product->getId(), ['hard' => 'true']);

        $body = self::assertErrorResponse($response, Response::HTTP_CONFLICT);
        static::assertSame('PRODUCT_IN_USE', $body['type']);
        $this->assertDatabaseHas(Product::class, ['id' => $product->getId()]);
    }
}
