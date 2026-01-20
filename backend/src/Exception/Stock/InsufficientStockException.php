<?php

declare(strict_types = 1);

namespace App\Exception\Stock;

use App\Exception\ApiException;
use App\Exception\ApiProblem;

class InsufficientStockException extends ApiException
{
    public function __construct(int $requested, int $available)
    {
        parent::__construct(new ApiProblem(
            title: 'Insufficient stock',
            type: 'INSUFFICIENT_STOCK',
            code: 400,
            extraData: [
                'requested' => $requested,
                'available' => $available,
                'message' => sprintf(
                    'Cannot consume %d units, only %d available at this location',
                    $requested,
                    $available
                )
            ]
        ));
    }
}
