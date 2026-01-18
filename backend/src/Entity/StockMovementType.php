<?php

declare(strict_types = 1);

namespace App\Entity;

enum StockMovementType: string
{
    case ADD = 'ADD';
    case REMOVE = 'REMOVE';
    case ADJUST = 'ADJUST';
}
