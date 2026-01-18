<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateStockEntryRequest
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $location_id = null,

        #[Assert\Date]
        public ?string $best_before = null
    ) {
    }
}
