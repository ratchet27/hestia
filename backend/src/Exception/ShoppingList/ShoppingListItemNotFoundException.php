<?php

declare(strict_types = 1);

namespace App\Exception\ShoppingList;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\Uid\Uuid;

class ShoppingListItemNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Shopping list item not found',
            type: 'SHOPPING_LIST_ITEM_NOT_FOUND',
            code: 404,
            extraData: ['id' => (string) $id]
        ));
    }
}
