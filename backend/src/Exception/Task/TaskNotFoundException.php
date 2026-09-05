<?php

declare(strict_types = 1);

namespace App\Exception\Task;

use App\Exception\EntityNotFoundException;
use Symfony\Component\Uid\Uuid;

final class TaskNotFoundException extends EntityNotFoundException
{
    public function __construct(Uuid $id)
    {
        parent::__construct('Task not found', 'TASK_NOT_FOUND', $id);
    }
}
