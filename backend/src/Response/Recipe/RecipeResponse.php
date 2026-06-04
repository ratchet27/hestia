<?php

declare(strict_types = 1);

namespace App\Response\Recipe;

use Symfony\Component\Uid\Uuid;

// @mago-ignore lint:excessive-parameter-list
final readonly class RecipeResponse
{
    /**
     * @param RecipeIngredientResponse[] $ingredients
     */
    public function __construct(
        public Uuid $id,
        public string $name,
        public ?string $instructions,
        public ?string $source_url,
        public bool $cookable,
        public array $ingredients,
        public \DateTimeImmutable $created_at
    ) {
    }
}
