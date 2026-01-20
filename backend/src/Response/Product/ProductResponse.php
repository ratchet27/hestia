<?php

declare(strict_types = 1);

namespace App\Response\Product;

use App\Entity\Product;
use App\Response\Barcode\BarcodeResponse;
use App\Response\Category\CategoryResponse;
use App\Response\Location\LocationResponse;
use App\Response\Stock\StockSummaryResponse;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;
use Symfony\Component\Uid\Uuid;

#[Map(source: Product::class)]
final readonly class ProductResponse
{
    /**
     * @param BarcodeResponse[] $barcodes
     */
    // @mago-ignore lint:excessive-parameter-list
    public function __construct(
        public Uuid $id,
        public string $name,
        public string $unit,
        public CategoryResponse $category,
        public LocationResponse $defaultLocation,
        public ?int $defaultExpiryDays,
        public int $minStock,
        public bool $active,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt,
        #[Map(transform: MapCollection::class)]
        public array $barcodes = [],
        #[Map(if: false)]
        public ?StockSummaryResponse $stock_summary = null
    ) {
    }
}
