<?php

declare(strict_types = 1);

namespace App\Exception\Common;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class InvalidUuidException extends ApiException
{
    public function __construct(string $value)
    {
        parent::__construct(new ApiProblem(
            title: 'Invalid UUID format',
            type: 'INVALID_UUID',
            code: Response::HTTP_BAD_REQUEST,
            extraData: ['value' => $value]
        ));
    }
}
