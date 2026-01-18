<?php

declare(strict_types=1);

namespace App\Response\Stock;

use Symfony\Component\Uid\Uuid;

final readonly class LocationQuantityResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public int $quantity
    ) {
    }
}
