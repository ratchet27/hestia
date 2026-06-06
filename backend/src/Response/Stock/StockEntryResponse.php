<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Entity\StockEntry;
use App\Response\Location\LocationResponse;
use Symfony\Component\Uid\Uuid;

// @mago-ignore lint:excessive-parameter-list
final readonly class StockEntryResponse
{
    public function __construct(
        public Uuid $id,
        public ProductBriefResponse $product,
        public LocationResponse $location,
        public ?string $best_before,
        public \DateTimeImmutable $created_at,
        public ?int $days_until_expiry
    ) {
    }

    public static function fromEntity(StockEntry $entry, ?int $daysUntilExpiry): self
    {
        return new self(
            id: $entry->getId(),
            product: ProductBriefResponse::fromEntity($entry->getProduct()),
            location: new LocationResponse(id: $entry->getLocation()->getId(), name: $entry->getLocation()->getName()),
            best_before: $entry->getBestBefore()?->format('Y-m-d'),
            created_at: $entry->getCreatedAt(),
            days_until_expiry: $daysUntilExpiry
        );
    }
}
