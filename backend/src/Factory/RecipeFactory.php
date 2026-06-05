<?php

declare(strict_types = 1);

namespace App\Factory;

use App\Entity\Recipe;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Recipe>
 */
final class RecipeFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Recipe::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(2, true),
            'instructions' => self::faker()->optional(0.3)->paragraph(),
            'sourceUrl' => null
        ];
    }
}
