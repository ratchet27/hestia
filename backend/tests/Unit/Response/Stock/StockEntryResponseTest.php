<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Response\Stock;

use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Response\Stock\StockEntryResponse;
use PHPUnit\Framework\TestCase;

final class StockEntryResponseTest extends TestCase
{
    public function testFromEntityMapsFieldsAndFormatsBestBeforeAsDate(): void
    {
        $entry = new StockEntry()
            ->setProduct(
                new Product()
                    ->setName('Milk')
                    ->setUnit('pcs')
            )
            ->setLocation(new Location()->setName('Fridge'))
            ->setBestBefore(new \DateTimeImmutable('2026-06-10 14:30:00'));

        $response = StockEntryResponse::fromEntity($entry, 4);

        static::assertSame($entry->getId(), $response->id);
        static::assertSame('Milk', $response->product->name);
        static::assertSame('Fridge', $response->location->name);
        static::assertSame('2026-06-10', $response->best_before);
        static::assertSame($entry->getCreatedAt(), $response->created_at);
        static::assertSame(4, $response->days_until_expiry);
    }

    public function testFromEntityKeepsNullBestBeforeAndNullDays(): void
    {
        $entry = new StockEntry()
            ->setProduct(
                new Product()
                    ->setName('Milk')
                    ->setUnit('pcs')
            )
            ->setLocation(new Location()->setName('Pantry'));

        $response = StockEntryResponse::fromEntity($entry, null);

        static::assertNull($response->best_before);
        static::assertNull($response->days_until_expiry);
    }
}
