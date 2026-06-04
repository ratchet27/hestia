<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RecipeIngredientPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $product_id,

        #[Assert\Positive]
        public int $required_count = 1,

        public bool $consume_on_cook = true
    ) {
    }
}
