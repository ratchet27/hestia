<?php

declare(strict_types = 1);

namespace App\Exception\Category;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class CategoryNameTakenException extends ApiException
{
    public function __construct(string $name)
    {
        parent::__construct(new ApiProblem(
            title: 'Category name already exists',
            type: 'CATEGORY_NAME_TAKEN',
            code: Response::HTTP_CONFLICT,
            extraData: ['name' => $name]
        ));
    }
}
