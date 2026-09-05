<?php

declare(strict_types = 1);

namespace App\Tests\Factory;

use App\Entity\ShoppingListItem;
use App\Enum\ShoppingListSource;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ShoppingListItem>
 */
final class ShoppingListItemFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ShoppingListItem::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'product' => ProductFactory::new(),
            'customName' => null,
            'amount' => self::faker()->numberBetween(1, 10),
            'note' => self::faker()->optional(0.3)->sentence(),
            'source' => ShoppingListSource::MANUAL,
            'done' => false
        ];
    }
}
