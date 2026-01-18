<?php

declare(strict_types = 1);

namespace App\Exception\Barcode;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class BarcodeNotFoundException extends ApiException
{
    public function __construct(string $code)
    {
        parent::__construct(new ApiProblem(
            title: 'Barcode not found',
            type: 'BARCODE_NOT_FOUND',
            code: Response::HTTP_NOT_FOUND,
            extraData: ['barcode' => $code]
        ));
    }
}
