<?php

declare(strict_types=1);

namespace App\Response\Stock;

use Symfony\Component\Uid\Uuid;

final readonly class ConsumeResultResponse
{
    /**
     * @param Uuid[] $deleted_entries
     */
    public function __construct(
        public int $consumed,
        public array $deleted_entries,
        public int $remaining_at_location
    ) {
    }
}
