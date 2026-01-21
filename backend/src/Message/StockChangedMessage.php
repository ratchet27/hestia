<?php

declare(strict_types = 1);

namespace App\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Message dispatched when stock levels change for a product.
 */
final readonly class StockChangedMessage
{
    public function __construct(
        public Uuid $productId,
        public int $previousQuantity,
        public int $newQuantity
    ) {
    }
}
