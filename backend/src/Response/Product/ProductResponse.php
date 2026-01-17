<?php

declare(strict_types = 1);

namespace App\Response\Product;

use App\Entity\Product;
use App\Response\Barcode\BarcodeResponse;
use App\Response\Category\CategoryResponse;
use App\Response\Location\LocationResponse;

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
        public LocationResponse $default_location,
        public ?int $default_expiry_days,
        public int $min_stock,
        public bool $active,
        public string $created_at,
        public ?string $updated_at,
        public array $barcodes = []
    ) {
    }

    public static function fromEntity(Product $product, bool $includeBarcodes = false): self
    {
        $barcodes = [];

        if ($includeBarcodes) {
            foreach ($product->getBarcodes() as $barcode) {
                $barcodes[] = BarcodeResponse::fromEntity($barcode);
            }
        }

        return new self(
            id: (string) $product->getId(),
            name: $product->getName(),
            category: CategoryResponse::fromEntity($product->getCategory()),
            default_location: LocationResponse::fromEntity($product->getDefaultLocation()),
            default_expiry_days: $product->getDefaultExpiryDays(),
            min_stock: $product->getMinStock(),
            active: $product->isActive(),
            created_at: $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updated_at: $product->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            barcodes: $barcodes
        );
    }
}
