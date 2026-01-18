<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConsumeStockRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $product_id,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $location_id,

        #[Assert\Positive]
        public int $quantity = 1
    ) {
    }
}
