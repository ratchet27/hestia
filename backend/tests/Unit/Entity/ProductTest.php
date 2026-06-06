<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Entity;

use App\Entity\Barcode;
use App\Entity\Product;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testAddBarcodeLinksBothSides(): void
    {
        $product = new Product();
        $barcode = new Barcode();

        $product->addBarcode($barcode);

        static::assertTrue($product->getBarcodes()->contains($barcode));
        static::assertSame($product, $barcode->getProduct());
    }

    public function testAddBarcodeIsIdempotent(): void
    {
        $product = new Product();
        $barcode = new Barcode();

        $product->addBarcode($barcode);
        $product->addBarcode($barcode);

        static::assertCount(1, $product->getBarcodes());
    }

    public function testRemoveBarcodeDetachesFromCollection(): void
    {
        $product = new Product();
        $barcode = new Barcode();
        $product->addBarcode($barcode);

        $product->removeBarcode($barcode);

        static::assertFalse($product->getBarcodes()->contains($barcode));
        static::assertCount(0, $product->getBarcodes());
    }
}
