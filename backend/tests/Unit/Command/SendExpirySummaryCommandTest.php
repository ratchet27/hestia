<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Command;

use App\Command\SendExpirySummaryCommand;
use App\Message\SendDailyExpirySummary;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SendExpirySummaryCommandTest extends TestCase
{
    public function testDispatchesTheSummaryMessage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(SendDailyExpirySummary::class))
            ->willReturn(new Envelope(new SendDailyExpirySummary()));

        $tester = new CommandTester(new SendExpirySummaryCommand($bus));
        $exitCode = $tester->execute([]);

        static::assertSame(0, $exitCode);
        $tester->assertCommandIsSuccessful();
    }
}
