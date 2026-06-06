<?php

declare(strict_types = 1);

namespace App\Exception\Product;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class InvalidLocationReferenceException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Invalid location reference',
            type: 'INVALID_LOCATION_REFERENCE',
            code: Response::HTTP_UNPROCESSABLE_ENTITY,
            extraData: ['id' => (string) $id]
        ));
    }
}
