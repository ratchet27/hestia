<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateStockMovementRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Product ID is required')]
        #[Assert\Uuid(message: 'Product ID must be a valid UUID')]
        public string $product_id,

        #[Assert\NotBlank(message: 'Location ID is required')]
        #[Assert\Uuid(message: 'Location ID must be a valid UUID')]
        public string $location_id,

        #[Assert\NotBlank(message: 'Movement type is required')]
        #[Assert\Choice(choices: ['ADD', 'REMOVE', 'ADJUST'], message: 'Invalid movement type')]
        public string $type,

        #[Assert\NotNull(message: 'Quantity is required')]
        #[Assert\Positive(message: 'Quantity must be positive')]
        public int $quantity,

        #[Assert\Length(max: 255, maxMessage: 'Notes cannot exceed 255 characters')]
        public ?string $notes = null
    ) {
    }
}
