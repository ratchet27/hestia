<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Service;

use App\Factory\LocationFactory;
use App\Factory\ProductFactory;
use App\Factory\StockEntryFactory;
use App\Repository\StockEntryRepository;
use App\Service\StockEntryService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class StockEntryConsumeAcrossLocationsTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testConsumesAcrossLocationsEarliestExpiryFirst(): void
    {
        self::bootKernel();
        $service = self::getContainer()->get(StockEntryService::class);
        $repo = self::getContainer()->get(StockEntryRepository::class);

        $product = ProductFactory::createOne();
        $fridge = LocationFactory::createOne(['name' => 'Fridge']);
        $pantry = LocationFactory::createOne(['name' => 'Pantry']);

        StockEntryFactory::createOne([
            'product' => $product,
            'location' => $fridge,
            'bestBefore' => new \DateTimeImmutable('2026-12-01')
        ]);
        $earliest = StockEntryFactory::createOne([
            'product' => $product,
            'location' => $pantry,
            'bestBefore' => new \DateTimeImmutable('2026-06-10')
        ]);

        $consumed = $service->consumeAcrossLocations($product->getId(), 1);

        static::assertSame(1, $consumed);
        static::assertNull($repo->find($earliest->getId()));
        static::assertSame(1, $repo->countByProduct($product->getId()));
    }

    public function testThrowsWhenInsufficientTotalStock(): void
    {
        self::bootKernel();
        $service = self::getContainer()->get(StockEntryService::class);
        $product = ProductFactory::createOne();
        StockEntryFactory::createOne(['product' => $product]);

        $this->expectException(\App\Exception\Stock\InsufficientStockException::class);
        $service->consumeAcrossLocations($product->getId(), 2);
    }
}
