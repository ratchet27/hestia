<?php

declare(strict_types = 1);

namespace App\Response\Location;

use App\Entity\Location;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: Location::class)]
final readonly class LocationResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public int $usageCount = 0
    ) {
    }
}
