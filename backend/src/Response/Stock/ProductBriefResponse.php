<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use Symfony\Component\Uid\Uuid;

final readonly class ProductBriefResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public string $unit
    ) {
    }
}
