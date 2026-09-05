<?php

declare(strict_types = 1);

namespace App\Exception\Stock;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class InsufficientStockException extends ApiException
{
    public function __construct(int $requested, int $available)
    {
        parent::__construct(new ApiProblem(
            title: 'Insufficient stock',
            type: 'INSUFFICIENT_STOCK',
            // 409: the request is well-formed; the current stock state conflicts with it
            // (same class of failure as RECIPE_NOT_COOKABLE).
            code: Response::HTTP_CONFLICT,
            extraData: ['requested' => $requested, 'available' => $available],
            detail: sprintf('Cannot consume %d units, only %d available at this location', $requested, $available)
        ));
    }
}
