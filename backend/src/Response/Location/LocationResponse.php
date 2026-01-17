<?php

declare(strict_types=1);

namespace App\Response\Location;

use App\Entity\Location;

final readonly class LocationResponse
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromEntity(Location $location): self
    {
        return new self(
            id: (string) $location->getId(),
            name: $location->getName(),
        );
    }
}
