<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Schedule;

use App\Message\SendDailyExpirySummary;
use App\Schedule\MainSchedule;
use App\Service\Time\AppTimezone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;

final class MainScheduleTest extends TestCase
{
    public function testScheduleHasDailySummaryAtConfiguredTime(): void
    {
        $schedule = new MainSchedule('08:30', new AppTimezone())->getSchedule();

        $messages = $schedule->getRecurringMessages();
        static::assertCount(1, $messages);

        $recurring = $messages[0];
        static::assertInstanceOf(RecurringMessage::class, $recurring);

        static::assertStringContainsString('30 8 * * *', (string) $recurring->getTrigger());

        $payloads = [...$recurring->getMessages($this->contextFor($recurring))];
        static::assertCount(1, $payloads);
        static::assertInstanceOf(SendDailyExpirySummary::class, $payloads[0]);
    }

    private function contextFor(RecurringMessage $recurring): MessageContext
    {
        return new MessageContext('main', $recurring->getId(), $recurring->getTrigger(), new \DateTimeImmutable());
    }
}
