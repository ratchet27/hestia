<?php

declare(strict_types = 1);

namespace App\Service\Time;

use Symfony\Component\Clock\ClockInterface;

/**
 * Single source of truth for "what day is today" and day-deltas in the household timezone.
 *
 * The system clock is pinned to UTC; the household runs at a fixed offset (see AppTimezone).
 * Routing all stock date math through here keeps the SPA and the Telegram summary in agreement.
 */
final readonly class HouseholdCalendar
{
    public function __construct(
        private ClockInterface $clock,
        private AppTimezone $appTimezone
    ) {
    }

    /** Midnight today in the household timezone. */
    public function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->appTimezone->get())->setTime(0, 0);
    }

    /**
     * Signed whole-day difference today -> $date, date-only (negative = already past).
     *
     * The date part of $date is read in $date's own timezone; pass a date-only value
     * (e.g. a Doctrine DATE, midnight UTC) for unambiguous results.
     */
    public function daysUntil(\DateTimeImmutable $date): int
    {
        $a = new \DateTimeImmutable($this->today()->format('Y-m-d'));
        $b = new \DateTimeImmutable($date->format('Y-m-d'));

        return (int) $a->diff($b)->format('%r%a');
    }

    /** Window cutoff date for "expiring within N days" queries. */
    public function expiryCutoff(int $days): \DateTimeImmutable
    {
        return $this->today()->modify(sprintf('%d days', $days));
    }
}
