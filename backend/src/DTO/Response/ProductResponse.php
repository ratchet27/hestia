<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Product;

final readonly class ProductResponse
{
    /**
     * @param array<int, array{id: string, barcode: string, created_at: string}> $barcodes
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $category_id,
        public string $category_name,
        public string $default_location_id,
        public string $default_location_name,
        public ?int $default_expiry_days,
        public int $min_stock,
        public bool $active,
        public string $created_at,
        public string $updated_at,
        public array $barcodes = [],
    ) {}

    public static function fromEntity(Product $product, bool $includeBarcodes = false): self
    {
        $barcodes = [];

        if ($includeBarcodes) {
            foreach ($product->getBarcodes() as $barcode) {
                $barcodes[] = [
                    'id' => (string) $barcode->getId(),
                    'barcode' => $barcode->getBarcode(),
                    'created_at' => $barcode->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ];
            }
        }

        return new self(
            id: (string) $product->getId(),
            name: $product->getName(),
            category_id: (string) $product->getCategory()->getId(),
            category_name: $product->getCategory()->getName(),
            default_location_id: (string) $product->getDefaultLocation()->getId(),
            default_location_name: $product->getDefaultLocation()->getName(),
            default_expiry_days: $product->getDefaultExpiryDays(),
            min_stock: $product->getMinStock(),
            active: $product->isActive(),
            created_at: $product->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updated_at: $product->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            barcodes: $barcodes,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'category_name' => $this->category_name,
            'default_location_id' => $this->default_location_id,
            'default_location_name' => $this->default_location_name,
            'default_expiry_days' => $this->default_expiry_days,
            'min_stock' => $this->min_stock,
            'active' => $this->active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'barcodes' => $this->barcodes,
        ];
    }
}
