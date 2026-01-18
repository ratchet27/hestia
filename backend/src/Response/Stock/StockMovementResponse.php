<?php

declare(strict_types = 1);

namespace App\Response\Stock;

use App\Entity\StockMovement;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: StockMovement::class)]
final readonly class StockMovementResponse
{
    // @mago-ignore lint:excessive-parameter-list
    public function __construct(
        public string $id,
        public StockResponse $stock,
        public string $type,
        public int $quantity,
        public ?string $notes,
        public string $created_at
    ) {
    }
}
