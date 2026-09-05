<?php

declare(strict_types = 1);

namespace App\Exception\ShoppingList;

use App\Exception\EntityNotFoundException;
use Symfony\Component\Uid\Uuid;

final class ShoppingListItemNotFoundException extends EntityNotFoundException
{
    public function __construct(Uuid $id)
    {
        parent::__construct('Shopping list item not found', 'SHOPPING_LIST_ITEM_NOT_FOUND', $id);
    }
}
