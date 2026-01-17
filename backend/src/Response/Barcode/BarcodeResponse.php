<?php

declare(strict_types=1);

namespace App\Response\Barcode;

use App\Entity\Barcode;

final readonly class BarcodeResponse
{
    public function __construct(
        public string $id,
        public string $barcode,
        public string $product_id,
        public string $created_at,
    ) {}

    public static function fromEntity(Barcode $barcode): self
    {
        return new self(
            id: (string) $barcode->getId(),
            barcode: $barcode->getBarcode(),
            product_id: (string) $barcode->getProduct()->getId(),
            created_at: $barcode->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
