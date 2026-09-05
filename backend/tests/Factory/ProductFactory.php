<?php

declare(strict_types = 1);

namespace App\Tests\Factory;

use App\Entity\Product;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Product>
 */
final class ProductFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Product::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->words(3, true),
            'category' => CategoryFactory::new(),
            'defaultLocation' => LocationFactory::new(),
            'defaultExpiryDays' => self::faker()->optional(0.3)->numberBetween(1, 365),
            'minStock' => self::faker()->numberBetween(0, 10),
            'active' => true
        ];
    }
}
