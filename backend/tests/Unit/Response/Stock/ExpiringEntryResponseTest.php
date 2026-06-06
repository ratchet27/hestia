<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Response\Stock;

use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Response\Stock\ExpiringEntryResponse;
use PHPUnit\Framework\TestCase;

final class ExpiringEntryResponseTest extends TestCase
{
    public function testFromEntityMapsFieldsWithNonNullBestBefore(): void
    {
        $entry = new StockEntry()
            ->setProduct(
                new Product()
                    ->setName('Yogurt')
                    ->setUnit('pcs')
            )
            ->setLocation(new Location()->setName('Fridge'))
            ->setBestBefore(new \DateTimeImmutable('2026-06-07'));

        $response = ExpiringEntryResponse::fromEntity($entry, 1);

        static::assertSame('2026-06-07', $response->best_before);
        static::assertSame(1, $response->days_until_expiry);
        static::assertSame('Yogurt', $response->product->name);
        static::assertSame('Fridge', $response->location->name);
    }
}
