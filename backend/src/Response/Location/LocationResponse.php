<?php

declare(strict_types = 1);

namespace App\Response\Location;

use App\Entity\Location;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Location::class)]
final readonly class LocationResponse
{
    public function __construct(
        public string $id,
        public string $name
    ) {
    }
}
