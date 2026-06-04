<?php

declare(strict_types = 1);

namespace App\Response\Recipe;

use Symfony\Component\Uid\Uuid;

// @mago-ignore lint:excessive-parameter-list
final readonly class RecipeIngredientResponse
{
    public function __construct(
        public Uuid $id,
        public Uuid $product_id,
        public string $product_name,
        public int $required_count,
        public bool $consume_on_cook,
        public int $in_stock,
        public bool $has_enough,
        public int $shortfall,
        public bool $product_inactive
    ) {
    }
}
