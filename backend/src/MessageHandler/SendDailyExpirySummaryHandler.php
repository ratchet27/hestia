<?php

declare(strict_types = 1);

namespace App\MessageHandler;

use App\Message\SendDailyExpirySummary;
use App\Repository\StockEntryRepository;
use App\Service\Telegram\ExpirySummaryBuilder;
use App\Service\Telegram\TelegramSender;
use App\Service\Time\HouseholdCalendar;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendDailyExpirySummaryHandler
{
    private const int WINDOW_DAYS = 3;

    public function __construct(
        private StockEntryRepository $stockEntryRepository,
        private ExpirySummaryBuilder $builder,
        private TelegramSender $sender,
        private LoggerInterface $logger,
        private HouseholdCalendar $calendar
    ) {
    }

    public function __invoke(SendDailyExpirySummary $message): void
    {
        $entries = $this->stockEntryRepository->findExpiring($this->calendar->expiryCutoff(self::WINDOW_DAYS));
        $summary = $this->builder->build($entries);

        if ($summary === null) {
            $this->logger->info('Daily expiry summary skipped', ['expiring' => count($entries)]);

            return;
        }

        $this->sender->send($summary);
        $this->logger->info('Daily expiry summary sent', ['expiring' => count($entries)]);
    }
}
