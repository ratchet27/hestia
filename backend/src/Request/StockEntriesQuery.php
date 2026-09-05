<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string for GET /stocks/entries. Validated at the edge so a malformed
 * id is a 422, not an uncaught \InvalidArgumentException from Uuid::fromString.
 */
final readonly class StockEntriesQuery
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $location = null,

        #[Assert\Uuid]
        public ?string $product = null
    ) {
    }
}
