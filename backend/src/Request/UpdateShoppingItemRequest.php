<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateShoppingItemRequest
{
    public function __construct(
        #[Assert\Positive]
        public ?int $amount = null,

        #[Assert\Length(max: 500)]
        public ?string $note = null,

        public ?bool $done = null
    ) {
    }
}
