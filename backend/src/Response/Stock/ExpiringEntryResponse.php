<?php

declare(strict_types=1);

namespace App\Response\Stock;

use App\Response\Location\LocationResponse;
use Symfony\Component\Uid\Uuid;

final readonly class ExpiringEntryResponse
{
    public function __construct(
        public Uuid $id,
        public ProductBriefResponse $product,
        public LocationResponse $location,
        public string $best_before,
        public int $days_until_expiry
    ) {
    }
}
