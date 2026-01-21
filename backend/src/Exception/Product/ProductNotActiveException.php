<?php

declare(strict_types = 1);

namespace App\Exception\Product;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ProductNotActiveException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Product is not active',
            type: 'PRODUCT_NOT_ACTIVE',
            code: Response::HTTP_BAD_REQUEST,
            extraData: ['id' => (string) $id]
        ));
    }
}
