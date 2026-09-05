<?php

declare(strict_types = 1);

namespace App\Exception\Stock;

use App\Exception\EntityNotFoundException;
use Symfony\Component\Uid\Uuid;

final class StockEntryNotFoundException extends EntityNotFoundException
{
    public function __construct(Uuid $id)
    {
        parent::__construct('Stock entry not found', 'STOCK_ENTRY_NOT_FOUND', $id);
    }
}
