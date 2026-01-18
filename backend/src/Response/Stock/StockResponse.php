<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Entity\Stock;
use App\ObjectMapper\Transform\MapLocation;
use App\ObjectMapper\Transform\MapProduct;
use App\Response\Location\LocationResponse;
use App\Response\Product\ProductResponse;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: Stock::class)]
final readonly class StockResponse
{
    public function __construct(
        public Uuid $id,
        #[Map(transform: MapProduct::class)]
        public ProductResponse $product,
        #[Map(transform: MapLocation::class)]
        public LocationResponse $location,
        public int $quantity,
        public \DateTimeImmutable $updatedAt
    ) {
    }
}
