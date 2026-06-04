<?php

declare(strict_types = 1);

namespace App\Response\Category;

use Symfony\Component\Uid\Uuid;

final readonly class CategoryListItemResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public int $usageCount
    ) {
    }
}
