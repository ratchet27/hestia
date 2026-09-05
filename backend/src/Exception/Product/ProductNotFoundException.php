<?php

declare(strict_types = 1);

namespace App\Exception\Product;

use App\Exception\EntityNotFoundException;
use Symfony\Component\Uid\Uuid;

final class ProductNotFoundException extends EntityNotFoundException
{
    public function __construct(Uuid $id)
    {
        parent::__construct('Product not found', 'PRODUCT_NOT_FOUND', $id);
    }
}
