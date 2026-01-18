<?php

declare(strict_types = 1);

namespace App\Response\Barcode;

use App\Entity\Barcode;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: Barcode::class)]
final readonly class BarcodeResponse
{
    public function __construct(
        public Uuid $id,
        public string $barcode,
        public string $productId,
        public \DateTimeImmutable $createdAt
    ) {
    }
}
