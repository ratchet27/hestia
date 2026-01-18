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
        public ?string $categoryId = null,

        #[Assert\Uuid]
        public ?string $defaultLocationId = null,

        #[Assert\Positive]
        public ?int $defaultExpiryDays = null,

        #[Assert\PositiveOrZero]
        public ?int $minStock = null,

        public ?bool $active = null,

        public bool $clearDefaultExpiryDays = false
    ) {
    }
}
