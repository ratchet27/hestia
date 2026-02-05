<?php

declare(strict_types = 1);

namespace App\Exception\Chore;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ChoreNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Chore not found',
            type: 'CHORE_NOT_FOUND',
            code: Response::HTTP_NOT_FOUND,
            extraData: ['id' => (string) $id]
        ));
    }
}
