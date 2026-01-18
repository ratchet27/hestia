<?php

declare(strict_types = 1);

namespace App\Response\Barcode;

use App\Entity\Barcode;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Barcode::class)]
final readonly class BarcodeResponse
{
    public function __construct(
        public string $id,
        public string $barcode,
        #[Map(source: 'productId')]
        public string $product_id,
        #[Map(source: 'createdAt')]
        public string $created_at
    ) {
    }
}
