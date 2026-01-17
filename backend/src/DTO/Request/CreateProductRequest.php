<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProductRequest
{
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

        public bool $active = true,
    ) {}

    /** @return array{name: string, category_id: string, default_location_id: string, default_expiry_days: int|null, min_stock: int, active: bool} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'category_id' => $this->category_id,
            'default_location_id' => $this->default_location_id,
            'default_expiry_days' => $this->default_expiry_days,
            'min_stock' => $this->min_stock,
            'active' => $this->active,
        ];
    }
}
