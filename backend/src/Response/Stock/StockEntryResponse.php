<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Response\Location\LocationResponse;
use Symfony\Component\Uid\Uuid;

final readonly class StockEntryResponse
{
    public ?int $days_until_expiry;

    public function __construct(
        public Uuid $id,
        public ProductBriefResponse $product,
        public LocationResponse $location,
        public ?string $best_before,
        public \DateTimeImmutable $created_at
    ) {
        $this->days_until_expiry = $this->calculateDaysUntilExpiry($best_before);
    }

    private function calculateDaysUntilExpiry(?string $bestBefore): ?int
    {
        if ($bestBefore === null) {
            return null;
        }

        $today = new \DateTimeImmutable('today');
        $bestBeforeDate = new \DateTimeImmutable($bestBefore);
        $diff = $today->diff($bestBeforeDate);

        return $diff->invert ? -$diff->days : $diff->days;
    }
}
