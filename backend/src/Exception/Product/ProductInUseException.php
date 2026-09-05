<?php

declare(strict_types = 1);

namespace App\Exception\Product;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ProductInUseException extends ApiException
{
    public function __construct(Uuid $productId, string $usedBy)
    {
        parent::__construct(new ApiProblem(
            title: 'Product is in use',
            type: 'PRODUCT_IN_USE',
            code: Response::HTTP_CONFLICT,
            extraData: ['id' => (string) $productId, 'used_by' => $usedBy],
            detail: sprintf('Cannot delete product %s: still referenced by %s', $productId, $usedBy)
        ));
    }
}
