<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddShoppingItemRequest
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $product_id = null,

        #[Assert\Length(max: 255)]
        public ?string $custom_name = null,

        #[Assert\Positive]
        public int $amount = 1,

        #[Assert\Length(max: 500)]
        public ?string $note = null
    ) {
    }
}
