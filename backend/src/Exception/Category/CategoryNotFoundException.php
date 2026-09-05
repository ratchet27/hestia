<?php

declare(strict_types = 1);

namespace App\Exception\Category;

use App\Exception\EntityNotFoundException;
use Symfony\Component\Uid\Uuid;

final class CategoryNotFoundException extends EntityNotFoundException
{
    public function __construct(Uuid $id)
    {
        parent::__construct('Category not found', 'CATEGORY_NOT_FOUND', $id);
    }
}
