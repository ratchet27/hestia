<?php

declare(strict_types = 1);

namespace App\Response\Stock;

final readonly class StockLocationResponse
{
    public function __construct(
        public string $location_id,
        public string $location_name,
        public int $quantity
    ) {
    }
}
