<?php

declare(strict_types = 1);

namespace App\Factory;

use App\Entity\StockEntry;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<StockEntry>
 */
final class StockEntryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return StockEntry::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'product' => ProductFactory::new(),
            'location' => LocationFactory::new(),
            'bestBefore' => self::faker()->optional(0.7)->dateTimeBetween('now', '+30 days')
        ];
    }
}
