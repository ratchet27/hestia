<?php

declare(strict_types = 1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Message\SendDailyExpirySummary;
use App\MessageHandler\SendDailyExpirySummaryHandler;
use App\Repository\StockEntryRepository;
use App\Service\Telegram\ExpirySummaryBuilder;
use App\Service\Telegram\TelegramSender;
use App\Service\Time\AppTimezone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

final class SendDailyExpirySummaryHandlerTest extends TestCase
{
    public function testSendsWhenSummaryNotEmpty(): void
    {
        // Local "today" = 2026-06-04 (09:00 Almaty); a same-day entry yields a non-null summary.
        $entry = new StockEntry()
            ->setProduct(new Product()->setName('Молоко'))
            ->setLocation(new Location()->setName('Холодильник'))
            ->setBestBefore(new \DateTimeImmutable('2026-06-04'));

        $repo = $this->createMock(StockEntryRepository::class);
        $repo->expects(self::once())->method('findExpiring')->with(3)->willReturn([$entry]);

        $chatter = $this->createMock(ChatterInterface::class);
        $chatter
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static fn(ChatMessage $m): bool => str_contains(
                $m->getSubject(),
                'Молоко (Холодильник)'
            )));

        $this->handler($repo, $chatter)(new SendDailyExpirySummary());
    }

    public function testSendsNothingWhenSummaryIsNull(): void
    {
        // No expiring entries => builder returns null => nothing is sent.
        $repo = $this->createMock(StockEntryRepository::class);
        $repo->expects(self::once())->method('findExpiring')->with(3)->willReturn([]);

        $chatter = $this->createMock(ChatterInterface::class);
        $chatter->expects(self::never())->method('send');

        $this->handler($repo, $chatter)(new SendDailyExpirySummary());
    }

    private function handler(StockEntryRepository $repo, ChatterInterface $chatter): SendDailyExpirySummaryHandler
    {
        $builder = new ExpirySummaryBuilder(
            new MockClock(new \DateTimeImmutable('2026-06-04 04:00:00')),
            new AppTimezone()
        );

        return new SendDailyExpirySummaryHandler($repo, $builder, new TelegramSender($chatter));
    }
}
