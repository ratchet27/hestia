<?php

declare(strict_types=1);

namespace App\Response\Stock;

use App\Response\Location\LocationResponse;
use Symfony\Component\Uid\Uuid;

final readonly class StockEntryResponse
{
    public function __construct(
        public Uuid $id,
        public ProductBriefResponse $product,
        public LocationResponse $location,
        public ?string $best_before,
        public \DateTimeImmutable $created_at
    ) {
    }
}
