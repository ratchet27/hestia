<?php

declare(strict_types = 1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Template for every "<entity> not found" error: always 404, always
 * `{ title, type, code, id }`. Subclasses only supply the two strings.
 */
abstract class EntityNotFoundException extends ApiException
{
    protected function __construct(string $title, string $type, Uuid $id)
    {
        parent::__construct(new ApiProblem(title: $title, type: $type, code: Response::HTTP_NOT_FOUND, extraData: [
            'id' => (string) $id
        ]));
    }
}
