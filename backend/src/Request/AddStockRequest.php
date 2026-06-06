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
        // Cap per-request units (W5, #59): stock is one row per unit, so guard against fat-finger mass inserts. Consume is uncapped by design.
        #[Assert\LessThanOrEqual(value: 50, message: 'Quantity must not exceed {{ compared_value }}.')]
        public int $quantity = 1,

        #[Assert\Date]
        public ?string $best_before = null
    ) {
    }
}
