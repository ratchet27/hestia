<?php

declare(strict_types = 1);

namespace App\Response\Product;

use App\Entity\Product;
use App\Response\Barcode\BarcodeResponse;
use App\Response\Category\CategoryResponse;
use App\Response\Location\LocationResponse;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Product::class)]
final readonly class ProductResponse
{
    /**
     * @param BarcodeResponse[] $barcodes
     */
    // @mago-ignore lint:excessive-parameter-list
    public function __construct(
        public string $id,
        public string $name,
        public CategoryResponse $category,
        public LocationResponse $defaultLocation,
        public ?int $defaultExpiryDays,
        public int $minStock,
        public bool $active,
        public string $createdAt,
        public ?string $updatedAt,
        public array $barcodes = []
    ) {
    }
}
