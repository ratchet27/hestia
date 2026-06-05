<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service\Time;

use App\Service\Time\AppTimezone;
use App\Service\Time\HouseholdCalendar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class HouseholdCalendarTest extends TestCase
{
    private function calendarAt(string $utc): HouseholdCalendar
    {
        // Clock is UTC; AppTimezone converts to Asia/Almaty (+05) for "today".
        return new HouseholdCalendar(
            new MockClock(new \DateTimeImmutable($utc, new \DateTimeZone('UTC'))),
            new AppTimezone()
        );
    }

    public function testTodayUsesHouseholdTimezoneAcrossTheBoundary(): void
    {
        // 22:30Z == 03:30 on 2026-06-06 in Almaty (+05) -> local "today" is the 6th, not the 5th.
        self::assertSame('2026-06-06', $this->calendarAt('2026-06-05 22:30:00')->today()->format('Y-m-d'));
    }

    public function testDaysUntilIsZeroForLocalTodayInsideTheBoundaryWindow(): void
    {
        $calendar = $this->calendarAt('2026-06-05 22:30:00'); // local today = 2026-06-06

        self::assertSame(0, $calendar->daysUntil(new \DateTimeImmutable('2026-06-06')));
        self::assertSame(-1, $calendar->daysUntil(new \DateTimeImmutable('2026-06-05')));
        self::assertSame(2, $calendar->daysUntil(new \DateTimeImmutable('2026-06-08')));
    }

    public function testExpiryCutoffIsHouseholdTodayPlusDays(): void
    {
        self::assertSame('2026-06-13', $this->calendarAt('2026-06-05 22:30:00')->expiryCutoff(7)->format('Y-m-d'));
    }

    public function testTodayAtUtcMidnightIsStillSameDayInAlmaty(): void
    {
        // 00:00Z == 05:00 in Almaty (+05) — same calendar day, not the previous one.
        self::assertSame('2026-06-05', $this->calendarAt('2026-06-05 00:00:00')->today()->format('Y-m-d'));
    }

    public function testNoRegressionAwayFromTheBoundary(): void
    {
        // 06:00Z == 11:00 Almaty, same calendar day -> local today = 2026-06-05.
        $calendar = $this->calendarAt('2026-06-05 06:00:00');

        self::assertSame('2026-06-05', $calendar->today()->format('Y-m-d'));
        self::assertSame(0, $calendar->daysUntil(new \DateTimeImmutable('2026-06-05')));
    }
}
