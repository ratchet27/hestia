<?php

declare(strict_types = 1);

namespace App\Response\Location;

use Symfony\Component\Uid\Uuid;

final readonly class LocationListItemResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public int $usage_count
    ) {
    }
}
