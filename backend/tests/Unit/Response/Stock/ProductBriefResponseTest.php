<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Response\Stock;

use App\Entity\Product;
use App\Response\Stock\ProductBriefResponse;
use PHPUnit\Framework\TestCase;

final class ProductBriefResponseTest extends TestCase
{
    public function testFromEntityCopiesIdNameAndUnit(): void
    {
        $product = (new Product())->setName('Milk')->setUnit('pcs');

        $response = ProductBriefResponse::fromEntity($product);

        static::assertSame($product->getId(), $response->id);
        static::assertSame('Milk', $response->name);
        static::assertSame('pcs', $response->unit);
    }
}
