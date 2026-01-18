<?php

declare(strict_types = 1);

namespace App\Factory;

use App\Entity\StockMovement;
use App\Entity\StockMovementType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<StockMovement>
 */
final class StockMovementFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return StockMovement::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'stock' => StockFactory::new(),
            'type' => self::faker()->randomElement(StockMovementType::cases()),
            'quantity' => self::faker()->numberBetween(1, 50),
            'notes' => self::faker()->optional()->sentence()
        ];
    }
}
