<?php

declare(strict_types = 1);

namespace App\Factory;

use App\Entity\Stock;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Stock>
 */
final class StockFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Stock::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'product' => ProductFactory::new(),
            'location' => LocationFactory::new(),
            'quantity' => self::faker()->numberBetween(0, 100)
        ];
    }
}
