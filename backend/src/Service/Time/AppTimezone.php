<?php

declare(strict_types = 1);

namespace App\Service\Time;

/**
 * Resolves the household's scheduling timezone.
 *
 * Defaults to a fixed offset (always correct, immune to stale tzdata). Upgrades
 * to the preferred named zone only when that zone currently resolves to the same
 * offset as the fixed fallback — so a container with stale tzdata that maps the
 * named zone to the wrong offset transparently falls back to the safe fixed value.
 */
final readonly class AppTimezone
{
    /** A date after Kazakhstan's 2024-03-01 move to permanent UTC+5. */
    private const string REFERENCE = '2024-06-01T12:00:00+00:00';

    private \DateTimeZone $timezone;

    public function __construct(
        string $fixedOffset = '+05:00',
        string $preferredZone = 'Asia/Almaty'
    ) {
        $reference = new \DateTimeImmutable(self::REFERENCE);
        $named = new \DateTimeZone($preferredZone);
        $fixed = new \DateTimeZone($fixedOffset);

        $this->timezone = $named->getOffset($reference) === $fixed->getOffset($reference) ? $named : $fixed;
    }

    public function get(): \DateTimeZone
    {
        return $this->timezone;
    }
}
