<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProductRequest
{
    // @mago-ignore lint:excessive-parameter-list
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $category_id,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $default_location_id,

        #[Assert\Positive]
        public ?int $default_expiry_days = null,

        #[Assert\PositiveOrZero]
        public int $min_stock = 0,

        public bool $active = true
    ) {
    }
}
