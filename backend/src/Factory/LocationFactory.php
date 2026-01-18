<?php

declare(strict_types = 1);

namespace App\Factory;

use App\Entity\Location;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Location>
 */
final class LocationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Location::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->word()
        ];
    }
}
