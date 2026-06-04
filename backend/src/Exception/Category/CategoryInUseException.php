<?php

declare(strict_types = 1);

namespace App\Exception\Category;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class CategoryInUseException extends ApiException
{
    public function __construct(int $usageCount)
    {
        parent::__construct(new ApiProblem(
            title: 'Category is in use and cannot be deleted',
            type: 'CATEGORY_IN_USE',
            code: Response::HTTP_CONFLICT,
            extraData: ['usageCount' => $usageCount]
        ));
    }
}
