<?php

declare(strict_types = 1);

namespace App\Tests\Factory;

use App\Entity\Barcode;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Barcode>
 */
final class BarcodeFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Barcode::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'barcode' => self::faker()->unique()->ean13(),
            'product' => ProductFactory::new()
        ];
    }
}
