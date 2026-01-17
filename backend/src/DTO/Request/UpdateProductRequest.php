<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProductRequest
{
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

        public bool $clear_default_expiry_days = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->category_id !== null) {
            $data['category_id'] = $this->category_id;
        }

        if ($this->default_location_id !== null) {
            $data['default_location_id'] = $this->default_location_id;
        }

        if ($this->default_expiry_days !== null) {
            $data['default_expiry_days'] = $this->default_expiry_days;
        } elseif ($this->clear_default_expiry_days) {
            $data['default_expiry_days'] = null;
        }

        if ($this->min_stock !== null) {
            $data['min_stock'] = $this->min_stock;
        }

        if ($this->active !== null) {
            $data['active'] = $this->active;
        }

        return $data;
    }
}
