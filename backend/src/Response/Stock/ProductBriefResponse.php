<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Entity\Product;
use Symfony\Component\Uid\Uuid;

final readonly class ProductBriefResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public string $unit
    ) {
    }

    public static function fromEntity(Product $product): self
    {
        return new self(
            id: $product->getId(),
            name: $product->getName(),
            unit: $product->getUnit()
        );
    }
}
