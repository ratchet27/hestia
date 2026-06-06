<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ExpiringStockQuery
{
    public function __construct(
        #[Assert\PositiveOrZero(message: 'Days must be zero or greater.')]
        public int $days = 7
    ) {
    }
}
