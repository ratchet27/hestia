<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Entity\StockEntry;
use Symfony\Component\Uid\Uuid;

final readonly class AddedStockEntryResponse
{
    public function __construct(
        public Uuid $id,
        public ?string $best_before
    ) {
    }

    public static function fromEntity(StockEntry $entry): self
    {
        return new self(id: $entry->getId(), best_before: $entry->getBestBefore()?->format('Y-m-d'));
    }
}
