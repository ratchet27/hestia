<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Entity\StockMovement;
use App\Entity\StockMovementType;
use App\ObjectMapper\Transform\MapStock;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: StockMovement::class)]
final readonly class StockMovementResponse
{
    // @mago-ignore lint:excessive-parameter-list
    public function __construct(
        public Uuid $id,
        #[Map(transform: MapStock::class)]
        public StockResponse $stock,
        public StockMovementType $type,
        public int $quantity,
        public ?string $notes,
        public \DateTimeImmutable $createdAt
    ) {
    }
}
