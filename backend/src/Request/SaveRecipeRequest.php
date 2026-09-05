<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SaveRecipeRequest
{
    /**
     * @param RecipeIngredientPayload[] $ingredients
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        // recipe_ingredients has a unique (recipe_id, product_id) index; reject
        // duplicates here so they are a 422, not a constraint violation 500.
        #[Assert\Unique(
            normalizer: [self::class, 'ingredientProductId'],
            message: 'Each product may appear in a recipe only once.'
        )]
        public array $ingredients = [],

        public ?string $instructions = null,

        #[Assert\Length(max: 1024)]
        #[Assert\Url]
        public ?string $source_url = null
    ) {
    }

    /** Unique-key extractor for the ingredients constraint. */
    public static function ingredientProductId(mixed $ingredient): mixed
    {
        return $ingredient instanceof RecipeIngredientPayload ? $ingredient->product_id : $ingredient;
    }
}
