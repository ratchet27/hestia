<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class AddShoppingItemRequest
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $product_id = null,

        #[Assert\Length(max: 255)]
        public ?string $custom_name = null,

        #[Assert\Positive]
        public int $amount = 1,

        #[Assert\Length(max: 500)]
        public ?string $note = null
    ) {
    }

    /**
     * An item needs something to display: either a product or a custom name.
     * Without this, `POST /shopping-list {}` used to create a nameless row.
     */
    #[Assert\Callback]
    public function validateHasName(ExecutionContextInterface $context): void
    {
        if ($this->product_id !== null || trim((string) $this->custom_name) !== '') {
            return;
        }

        $context
            ->buildViolation('Either product_id or custom_name is required.')
            ->atPath('custom_name')
            ->addViolation();
    }
}
