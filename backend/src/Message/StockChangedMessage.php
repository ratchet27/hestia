<?php

declare(strict_types = 1);

namespace App\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Dispatched after a product's stock level changes, to reconcile the shopping list.
 * Carries only the product id; the handler re-queries the live stock count.
 */
final readonly class StockChangedMessage
{
    public function __construct(
        public Uuid $productId
    ) {
    }
}
