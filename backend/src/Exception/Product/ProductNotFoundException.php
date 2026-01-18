<?php

declare(strict_types = 1);

namespace App\Exception\Product;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ProductNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Product not found',
            type: 'PRODUCT_NOT_FOUND',
            code: Response::HTTP_NOT_FOUND,
            extraData: ['id' => (string) $id]
        ));
    }
}
