<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Entity\StockEntry;
use App\Response\Location\LocationResponse;
use Symfony\Component\Uid\Uuid;

final readonly class ExpiringEntryResponse
{
    public function __construct(
        public Uuid $id,
        public ProductBriefResponse $product,
        public LocationResponse $location,
        public string $best_before,
        public int $days_until_expiry
    ) {
    }

    public static function fromEntity(StockEntry $entry, int $daysUntilExpiry): self
    {
        /** @var \DateTimeImmutable $bestBefore - guaranteed non-null by the findExpiring query */
        $bestBefore = $entry->getBestBefore();

        return new self(
            id: $entry->getId(),
            product: ProductBriefResponse::fromEntity($entry->getProduct()),
            location: new LocationResponse(
                id: $entry->getLocation()->getId(),
                name: $entry->getLocation()->getName()
            ),
            best_before: $bestBefore->format('Y-m-d'),
            days_until_expiry: $daysUntilExpiry
        );
    }
}
