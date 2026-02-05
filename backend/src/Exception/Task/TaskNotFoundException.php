<?php

declare(strict_types = 1);

namespace App\Exception\Task;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class TaskNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Task not found',
            type: 'TASK_NOT_FOUND',
            code: Response::HTTP_NOT_FOUND,
            extraData: ['id' => (string) $id]
        ));
    }
}
