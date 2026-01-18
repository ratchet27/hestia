<?php

declare(strict_types = 1);

namespace App\Exception\Common;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class ValidationException extends ApiException
{
    /**
     * @param array<array{property: string, violation: string}> $errors
     */
    public function __construct(array $errors)
    {
        parent::__construct(new ApiProblem(
            title: 'Validation failed',
            type: 'VALIDATION_ERROR',
            code: Response::HTTP_UNPROCESSABLE_ENTITY,
            extraData: ['errors' => $errors]
        ));
    }
}
