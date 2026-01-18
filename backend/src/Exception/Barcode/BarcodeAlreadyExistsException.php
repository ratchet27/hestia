<?php

declare(strict_types = 1);

namespace App\Exception\Barcode;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class BarcodeAlreadyExistsException extends ApiException
{
    public function __construct(string $barcode)
    {
        parent::__construct(new ApiProblem(
            title: 'Barcode already exists',
            type: 'BARCODE_ALREADY_EXISTS',
            code: Response::HTTP_CONFLICT,
            extraData: ['barcode' => $barcode]
        ));
    }
}
