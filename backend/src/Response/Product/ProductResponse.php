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
        #[Map(source: 'defaultLocation')]
        public LocationResponse $default_location,
        #[Map(source: 'defaultExpiryDays')]
        public ?int $default_expiry_days,
        #[Map(source: 'minStock')]
        public int $min_stock,
        public bool $active,
        #[Map(source: 'createdAt')]
        public string $created_at,
        #[Map(source: 'updatedAt')]
        public ?string $updated_at,
        public array $barcodes = []
    ) {
    }
}
