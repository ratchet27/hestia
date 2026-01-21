<?php

declare(strict_types = 1);

namespace App\Enum;

enum ShoppingListSource: string
{
    case MANUAL = 'manual';
    case AUTO = 'auto';
    case RECIPE = 'recipe';
}
