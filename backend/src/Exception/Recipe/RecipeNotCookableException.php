<?php

declare(strict_types = 1);

namespace App\Exception\Recipe;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class RecipeNotCookableException extends ApiException
{
    /**
     * @param string[] $missing
     */
    public function __construct(Uuid $id, array $missing)
    {
        parent::__construct(new ApiProblem(
            title: 'Recipe is not cookable',
            type: 'RECIPE_NOT_COOKABLE',
            code: Response::HTTP_CONFLICT,
            extraData: ['id' => (string) $id, 'missing' => $missing],
            detail: 'Not all ingredients are in stock'
        ));
    }
}
