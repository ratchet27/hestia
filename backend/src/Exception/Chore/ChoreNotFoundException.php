<?php

declare(strict_types = 1);

namespace App\Exception\Chore;

use App\Exception\EntityNotFoundException;
use Symfony\Component\Uid\Uuid;

final class ChoreNotFoundException extends EntityNotFoundException
{
    public function __construct(Uuid $id)
    {
        parent::__construct('Chore not found', 'CHORE_NOT_FOUND', $id);
    }
}
