<?php

declare(strict_types = 1);

namespace App\Response\Stock;

final readonly class ProductSummaryResponse
{
    /**
     * @param LocationQuantityResponse[] $locations
     */
    public function __construct(
        public ProductBriefResponse $product,
        public int $total_quantity,
        public ?string $earliest_expiry,
        public array $locations
    ) {
    }
}
