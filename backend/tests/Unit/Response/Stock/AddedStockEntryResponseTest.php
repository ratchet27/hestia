<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Response\Stock;

use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Response\Stock\AddedStockEntryResponse;
use PHPUnit\Framework\TestCase;

final class AddedStockEntryResponseTest extends TestCase
{
    public function testFromEntityMapsIdAndFormatsDate(): void
    {
        $entry = new StockEntry()
            ->setProduct(
                new Product()
                    ->setName('Milk')
                    ->setUnit('pcs')
            )
            ->setLocation(new Location()->setName('Fridge'))
            ->setBestBefore(new \DateTimeImmutable('2026-06-10 14:30:00'));

        $response = AddedStockEntryResponse::fromEntity($entry);

        static::assertSame($entry->getId(), $response->id);
        static::assertSame('2026-06-10', $response->best_before);
    }

    public function testFromEntityKeepsNullBestBefore(): void
    {
        $entry = new StockEntry()
            ->setProduct(
                new Product()
                    ->setName('Milk')
                    ->setUnit('pcs')
            )
            ->setLocation(new Location()->setName('Pantry'));

        $response = AddedStockEntryResponse::fromEntity($entry);

        static::assertNull($response->best_before);
    }
}
