<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service\Telegram;

use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Service\Telegram\ExpirySummaryBuilder;
use App\Service\Time\AppTimezone;
use App\Service\Time\HouseholdCalendar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ExpirySummaryBuilderTest extends TestCase
{
    private function builderAt(string $utc): ExpirySummaryBuilder
    {
        // Clock is UTC; HouseholdCalendar converts to Asia/Almaty (+05) for "today".
        return new ExpirySummaryBuilder(
            new HouseholdCalendar(new MockClock(new \DateTimeImmutable($utc)), new AppTimezone())
        );
    }

    private function entry(string $product, string $location, string $bestBefore): StockEntry
    {
        return new StockEntry()
            ->setProduct(new Product()->setName($product))
            ->setLocation(new Location()->setName($location))
            ->setBestBefore(new \DateTimeImmutable($bestBefore));
    }

    public function testReturnsNullWhenNoEntries(): void
    {
        self::assertNull($this->builderAt('2026-06-04 04:00:00')->build([]));
    }

    public function testBuildsBothSectionsWithRelativeDates(): void
    {
        // Local "today" = 2026-06-04 (09:00 Almaty).
        $builder = $this->builderAt('2026-06-04 04:00:00');

        $text = $builder->build([
            $this->entry('Молоко', 'Холодильник', '2026-06-02'), // 2 дн. назад
            $this->entry('Сметана', 'Холодильник', '2026-06-03'), // вчера
            $this->entry('Йогурт', 'Холодильник', '2026-06-04'), // сегодня
            $this->entry('Хлеб', 'Кладовая', '2026-06-06') // через 2 дн.
        ]);

        self::assertNotNull($text);
        self::assertStringContainsString('🏠 Гестия — сводка на 04.06', $text);
        self::assertStringContainsString('⚠️ Просрочено', $text);
        self::assertStringContainsString('Молоко (Холодильник) — 2 дн. назад', $text);
        self::assertStringContainsString('Сметана (Холодильник) — вчера', $text);
        self::assertStringContainsString('🔔 Скоро истекает', $text);
        self::assertStringContainsString('Йогурт (Холодильник) — сегодня', $text);
        self::assertStringContainsString('Хлеб (Кладовая) — через 2 дн.', $text);
        // App name is Гестия, never Хестия.
        self::assertStringNotContainsString('Хестия', $text);
    }

    public function testOmitsExpiredSectionWhenNoneExpired(): void
    {
        $builder = $this->builderAt('2026-06-04 04:00:00');

        $text = $builder->build([$this->entry('Йогурт', 'Холодильник', '2026-06-05')]); // завтра

        self::assertNotNull($text);
        self::assertStringNotContainsString('⚠️ Просрочено', $text);
        self::assertStringContainsString('🔔 Скоро истекает', $text);
        self::assertStringContainsString('Йогурт (Холодильник) — завтра', $text);
    }

    public function testEscapesHtmlSpecialCharsInNames(): void
    {
        $builder = $this->builderAt('2026-06-04 04:00:00');

        $text = $builder->build([$this->entry('Сок <Rich & Co>', 'Кухня', '2026-06-04')]);

        self::assertNotNull($text);
        self::assertStringContainsString('Сок &lt;Rich &amp; Co&gt; (Кухня) — сегодня', $text);
    }
}
