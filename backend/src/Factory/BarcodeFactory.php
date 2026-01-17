<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Barcode;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Barcode>
 */
final class BarcodeFactory extends PersistentProxyObjectFactory
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
            'product' => ProductFactory::new(),
        ];
    }
}
