<?php

declare(strict_types = 1);

namespace App\Response\Stock;

final readonly class StockSummaryResponse
{
    /**
     * @param LocationQuantityResponse[] $locations
     */
    public function __construct(
        public int $total_quantity,
        public ?string $earliest_expiry,
        public array $locations
    ) {
    }
}
