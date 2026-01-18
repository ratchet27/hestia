<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddStockRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $product_id,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $location_id,

        #[Assert\Positive]
        public int $quantity = 1,

        #[Assert\Date]
        public ?string $best_before = null
    ) {
    }
}
