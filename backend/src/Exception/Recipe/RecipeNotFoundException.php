<?php

declare(strict_types = 1);

namespace App\Exception\Recipe;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class RecipeNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Recipe not found',
            type: 'RECIPE_NOT_FOUND',
            code: Response::HTTP_NOT_FOUND,
            extraData: ['recipe_id' => (string) $id]
        ));
    }
}
