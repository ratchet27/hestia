<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string for GET /products. Validated at the edge so a malformed
 * category id is a 422, not an uncaught \InvalidArgumentException.
 */
final readonly class ProductListQuery
{
    public function __construct(
        #[Assert\Length(max: 255)]
        public ?string $name = null,

        #[Assert\Uuid]
        public ?string $categoryId = null,

        public bool $includeArchived = false
    ) {
    }

    /** @return array{name?: string, category_id?: string, active?: bool} */
    public function toFilters(): array
    {
        $filters = [];

        if ($this->name !== null) {
            $filters['name'] = $this->name;
        }

        if ($this->categoryId !== null) {
            $filters['category_id'] = $this->categoryId;
        }

        // Default to active-only unless include_archived=true is specified
        if (!$this->includeArchived) {
            $filters['active'] = true;
        }

        return $filters;
    }
}
