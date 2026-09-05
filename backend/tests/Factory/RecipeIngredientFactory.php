<?php

declare(strict_types = 1);

namespace App\Tests\Factory;

use App\Entity\RecipeIngredient;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<RecipeIngredient>
 */
final class RecipeIngredientFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return RecipeIngredient::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'recipe' => RecipeFactory::new(),
            'product' => ProductFactory::new(),
            'requiredCount' => self::faker()->numberBetween(1, 3),
            'consumeOnCook' => true
        ];
    }
}
