<?php

declare(strict_types = 1);

namespace App\Service\Telegram;

use App\Entity\StockEntry;
use App\Service\Time\AppTimezone;
use Symfony\Component\Clock\ClockInterface;

final readonly class ExpirySummaryBuilder
{
    public function __construct(
        private ClockInterface $clock,
        private AppTimezone $appTimezone
    ) {
    }

    /**
     * @param StockEntry[] $entries entries with bestBefore <= today + window (expired included)
     */
    public function build(array $entries): ?string
    {
        $today = $this->today();

        $expired = [];
        $soon = [];
        foreach ($entries as $entry) {
            $bestBefore = $entry->getBestBefore();
            if ($bestBefore === null) {
                continue;
            }

            $days = $this->dayDelta($today, $bestBefore);
            $line = sprintf(
                '• %s (%s) — %s',
                $this->escape($entry->getProduct()->getName()),
                $this->escape($entry->getLocation()->getName()),
                $this->relative($days)
            );

            if ($days < 0) {
                $expired[] = $line;
            } else {
                $soon[] = $line;
            }
        }

        if ($expired === [] && $soon === []) {
            return null;
        }

        $sections = [sprintf('🏠 Гестия — сводка на %s', $today->format('d.m'))];
        if ($expired !== []) {
            $sections[] = "⚠️ Просрочено\n" . implode("\n", $expired);
        }

        if ($soon !== []) {
            $sections[] = "🔔 Скоро истекает\n" . implode("\n", $soon);
        }

        return implode("\n\n", $sections);
    }

    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->appTimezone->get());
    }

    /** Signed whole-day difference: bestBefore date minus today's date (negative = past). */
    private function dayDelta(\DateTimeImmutable $today, \DateTimeImmutable $bestBefore): int
    {
        $a = new \DateTimeImmutable($today->format('Y-m-d'));
        $b = new \DateTimeImmutable($bestBefore->format('Y-m-d'));

        return (int) $a->diff($b)->format('%r%a');
    }

    private function relative(int $days): string
    {
        return match (true) {
            $days <= -2 => sprintf('%d дн. назад', -$days),
            $days === -1 => 'вчера',
            $days === 0 => 'сегодня',
            $days === 1 => 'завтра',
            default => sprintf('через %d дн.', $days)
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
