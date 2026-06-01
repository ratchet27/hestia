<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service\Time;

use App\Service\Time\AppTimezone;
use PHPUnit\Framework\TestCase;

class AppTimezoneTest extends TestCase
{
    private const string POST_CHANGE_DATE = '2026-06-01T12:00:00+00:00';

    public function testEffectiveOffsetIsAlwaysPlusFive(): void
    {
        $reference = new \DateTimeImmutable(self::POST_CHANGE_DATE);

        $tz = new AppTimezone('+05:00', 'Asia/Almaty');

        static::assertSame(5 * 3600, $tz->get()->getOffset($reference));
    }

    public function testPrefersNamedZoneWhenItResolvesToPlusFive(): void
    {
        // Etc/GMT-5 is permanently +05:00 regardless of tzdata version.
        $tz = new AppTimezone('+05:00', 'Etc/GMT-5');

        static::assertSame('Etc/GMT-5', $tz->get()->getName());
    }

    public function testFallsBackToFixedOffsetWhenNamedZoneIsNotPlusFive(): void
    {
        // Asia/Bishkek is permanently +06:00 (no DST) -> must NOT be chosen.
        $tz = new AppTimezone('+05:00', 'Asia/Bishkek');

        static::assertSame('+05:00', $tz->get()->getName());
    }
}
