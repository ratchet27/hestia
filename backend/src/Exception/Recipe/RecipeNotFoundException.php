<?php

declare(strict_types = 1);

namespace App\Exception\Recipe;

use App\Exception\EntityNotFoundException;
use Symfony\Component\Uid\Uuid;

final class RecipeNotFoundException extends EntityNotFoundException
{
    public function __construct(Uuid $id)
    {
        parent::__construct('Recipe not found', 'RECIPE_NOT_FOUND', $id);
    }
}
