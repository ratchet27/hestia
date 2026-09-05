<?php

declare(strict_types = 1);

namespace App\Exception\Location;

use App\Exception\EntityNotFoundException;
use Symfony\Component\Uid\Uuid;

final class LocationNotFoundException extends EntityNotFoundException
{
    public function __construct(Uuid $id)
    {
        parent::__construct('Location not found', 'LOCATION_NOT_FOUND', $id);
    }
}
