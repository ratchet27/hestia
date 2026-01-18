<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Entity\Stock;
use App\Response\Location\LocationResponse;
use App\Response\Product\ProductResponse;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Stock::class)]
final readonly class StockResponse
{
    public function __construct(
        public string $id,
        public ProductResponse $product,
        public LocationResponse $location,
        public int $quantity,
        public string $updated_at
    ) {
    }
}
