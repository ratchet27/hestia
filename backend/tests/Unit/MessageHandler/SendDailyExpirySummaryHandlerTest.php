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
use App\Service\Time\HouseholdCalendar;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

final class SendDailyExpirySummaryHandlerTest extends TestCase
{
    private TestHandler $logHandler;

    public function testSendsWhenSummaryNotEmpty(): void
    {
        // Local "today" = 2026-06-04 (09:00 Almaty); a same-day entry yields a non-null summary.
        $entry = new StockEntry()
            ->setProduct(new Product()->setName('Молоко'))
            ->setLocation(new Location()->setName('Холодильник'))
            ->setBestBefore(new \DateTimeImmutable('2026-06-04'));

        $repo = $this->createMock(StockEntryRepository::class);
        $repo
            ->expects(self::once())
            ->method('findExpiring')
            ->with(self::callback(
                static fn(\DateTimeImmutable $cutoff): bool => $cutoff->format('Y-m-d') === '2026-06-07'
            ))
            ->willReturn([$entry]);

        $chatter = $this->createMock(ChatterInterface::class);
        $chatter
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static fn(ChatMessage $m): bool => str_contains(
                $m->getSubject(),
                'Молоко (Холодильник)'
            )));

        $this->handler($repo, $chatter)(new SendDailyExpirySummary());
        self::assertTrue($this->logHandler->hasInfoThatContains('Daily expiry summary sent'));
    }

    public function testSendsNothingWhenSummaryIsNull(): void
    {
        // No expiring entries => builder returns null => nothing is sent.
        $repo = $this->createMock(StockEntryRepository::class);
        $repo
            ->expects(self::once())
            ->method('findExpiring')
            ->with(self::callback(
                static fn(\DateTimeImmutable $cutoff): bool => $cutoff->format('Y-m-d') === '2026-06-07'
            ))
            ->willReturn([]);

        $chatter = $this->createMock(ChatterInterface::class);
        $chatter->expects(self::never())->method('send');

        $this->handler($repo, $chatter)(new SendDailyExpirySummary());
        self::assertTrue($this->logHandler->hasInfoThatContains('Daily expiry summary skipped'));
    }

    private function handler(StockEntryRepository $repo, ChatterInterface $chatter): SendDailyExpirySummaryHandler
    {
        $calendar = new HouseholdCalendar(
            new MockClock(new \DateTimeImmutable('2026-06-04 04:00:00')),
            new AppTimezone()
        );
        $builder = new ExpirySummaryBuilder($calendar);

        $this->logHandler = new TestHandler();

        return new SendDailyExpirySummaryHandler(
            $repo,
            $builder,
            new TelegramSender($chatter, new NullLogger()),
            new Logger('app', [$this->logHandler]),
            $calendar
        );
    }
}
