<?php

declare(strict_types = 1);

namespace App\Exception\Location;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class LocationInUseException extends ApiException
{
    public function __construct(int $usageCount)
    {
        parent::__construct(new ApiProblem(
            title: 'Location is in use and cannot be deleted',
            type: 'LOCATION_IN_USE',
            code: Response::HTTP_CONFLICT,
            extraData: ['usageCount' => $usageCount]
        ));
    }
}
