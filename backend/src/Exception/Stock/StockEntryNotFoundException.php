<?php

declare(strict_types=1);

namespace App\Exception\Stock;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\Uid\Uuid;

class StockEntryNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Stock entry not found',
            type: 'STOCK_ENTRY_NOT_FOUND',
            code: 404,
            extraData: ['id' => (string) $id]
        ));
    }
}
