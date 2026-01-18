<?php

declare(strict_types = 1);

namespace App\Response\Category;

use App\Entity\Category;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: Category::class)]
final readonly class CategoryResponse
{
    public function __construct(
        public Uuid $id,
        public string $name
    ) {
    }
}
