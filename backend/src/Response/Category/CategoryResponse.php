<?php

declare(strict_types = 1);

namespace App\Response\Category;

use App\Entity\Category;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Category::class)]
final readonly class CategoryResponse
{
    public function __construct(
        public string $id,
        public string $name
    ) {
    }
}
