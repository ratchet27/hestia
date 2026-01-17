<?php

declare(strict_types = 1);

namespace App\Response\Category;

use App\Entity\Category;

final readonly class CategoryResponse
{
    public function __construct(
        public string $id,
        public string $name
    ) {
    }

    public static function fromEntity(Category $category): self
    {
        return new self(id: (string) $category->getId(), name: $category->getName());
    }
}
