<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Location;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Location>
 */
final class LocationFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Location::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->word(),
        ];
    }
}
