<?php

declare(strict_types = 1);

namespace App\Exception\Product;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class LocationNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Location not found',
            type: 'LOCATION_NOT_FOUND',
            code: Response::HTTP_BAD_REQUEST,
            extraData: ['id' => (string) $id]
        ));
    }
}
