<?php

declare(strict_types = 1);

namespace App\Response\Stock;

final readonly class StockSummaryResponse
{
    /**
     * @param StockLocationResponse[] $locations
     */
    public function __construct(
        public int $total_quantity,
        public array $locations
    ) {
    }
}
