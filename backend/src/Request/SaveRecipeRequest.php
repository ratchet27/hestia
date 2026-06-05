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
        public array $ingredients = [],

        public ?string $instructions = null,

        #[Assert\Length(max: 1024)]
        #[Assert\Url]
        public ?string $source_url = null
    ) {
    }
}
