<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProductRequest
{
    // @mago-ignore lint:excessive-parameter-list
    public function __construct(
        #[Assert\Length(max: 255)]
        public ?string $name = null,

        #[Assert\Uuid]
        public ?string $category_id = null,

        #[Assert\Uuid]
        public ?string $default_location_id = null,

        #[Assert\Positive]
        public ?int $default_expiry_days = null,

        #[Assert\PositiveOrZero]
        public ?int $min_stock = null,

        public ?bool $active = null,

        public bool $clear_default_expiry_days = false
    ) {
    }
}
