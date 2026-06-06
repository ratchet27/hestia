<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Entity;

use App\Entity\Product;
use App\Entity\ShoppingListItem;
use App\Enum\ShoppingListSource;
use PHPUnit\Framework\TestCase;

final class ShoppingListItemTest extends TestCase
{
    public function testReviseAmountFlipsAutoToManualWhenAmountChanges(): void
    {
        $item = new ShoppingListItem()
            ->setAmount(2)
            ->setSource(ShoppingListSource::AUTO);

        $item->reviseAmount(5);

        static::assertSame(5, $item->getAmount());
        static::assertSame(ShoppingListSource::MANUAL, $item->getSource());
    }

    public function testReviseAmountKeepsAutoWhenAmountUnchanged(): void
    {
        $item = new ShoppingListItem()
            ->setAmount(3)
            ->setSource(ShoppingListSource::AUTO);

        $item->reviseAmount(3);

        static::assertSame(3, $item->getAmount());
        static::assertSame(ShoppingListSource::AUTO, $item->getSource());
    }

    public function testReviseAmountKeepsRecipeOnChange(): void
    {
        $item = new ShoppingListItem()
            ->setAmount(2)
            ->setSource(ShoppingListSource::RECIPE);

        $item->reviseAmount(7);

        static::assertSame(7, $item->getAmount());
        static::assertSame(ShoppingListSource::RECIPE, $item->getSource());
    }

    public function testReviseAmountKeepsManualOnChange(): void
    {
        $item = new ShoppingListItem()
            ->setAmount(2)
            ->setSource(ShoppingListSource::MANUAL);

        $item->reviseAmount(9);

        static::assertSame(9, $item->getAmount());
        static::assertSame(ShoppingListSource::MANUAL, $item->getSource());
    }

    public function testClaimManualFromAuto(): void
    {
        $item = new ShoppingListItem()->setSource(ShoppingListSource::AUTO);

        $item->claimManual();

        static::assertSame(ShoppingListSource::MANUAL, $item->getSource());
    }

    public function testClaimManualFromRecipe(): void
    {
        $item = new ShoppingListItem()->setSource(ShoppingListSource::RECIPE);

        $item->claimManual();

        static::assertSame(ShoppingListSource::MANUAL, $item->getSource());
    }

    public function testIsAutoReflectsSource(): void
    {
        $item = new ShoppingListItem();

        $item->setSource(ShoppingListSource::AUTO);
        static::assertTrue($item->isAuto());

        $item->setSource(ShoppingListSource::MANUAL);
        static::assertFalse($item->isAuto());

        $item->setSource(ShoppingListSource::RECIPE);
        static::assertFalse($item->isAuto());
    }

    public function testClaimManualFromManualStaysManual(): void
    {
        $item = new ShoppingListItem()->setSource(ShoppingListSource::MANUAL);

        $item->claimManual();

        static::assertSame(ShoppingListSource::MANUAL, $item->getSource());
    }

    public function testGetDisplayNamePrefersProductThenCustomNameThenEmpty(): void
    {
        $product = new Product();
        $product->setName('Milk');

        // Product present → product name wins.
        $withProduct = new ShoppingListItem()->setProduct($product)->setCustomName('ignored');
        static::assertSame('Milk', $withProduct->getDisplayName());

        // No product, custom name set → custom name.
        $withCustom = new ShoppingListItem()->setCustomName('Eggs');
        static::assertSame('Eggs', $withCustom->getDisplayName());

        // Neither → empty string.
        $empty = new ShoppingListItem();
        static::assertSame('', $empty->getDisplayName());
    }
}
