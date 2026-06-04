<?php

declare(strict_types = 1);

namespace App\Exception\Location;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class LocationNameTakenException extends ApiException
{
    public function __construct(string $name)
    {
        parent::__construct(new ApiProblem(
            title: 'Location name already exists',
            type: 'LOCATION_NAME_TAKEN',
            code: Response::HTTP_CONFLICT,
            extraData: ['name' => $name]
        ));
    }
}
