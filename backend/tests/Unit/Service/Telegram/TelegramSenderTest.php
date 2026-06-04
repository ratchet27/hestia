<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service\Telegram;

use App\Service\Telegram\TelegramSender;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Exception\RuntimeException as NotifierException;
use Symfony\Component\Notifier\Message\ChatMessage;

final class TelegramSenderTest extends TestCase
{
    public function testLogsAndRethrowsOnFailure(): void
    {
        $handler = new TestHandler();

        $chatter = $this->createStub(ChatterInterface::class);
        $chatter->method('send')->willThrowException(new NotifierException('boom'));

        $sender = new TelegramSender($chatter, new Logger('app', [$handler]));

        try {
            $sender->send('hello');
            self::fail('Expected the notifier exception to propagate');
        } catch (NotifierException) {
            // expected — propagation preserves Messenger retry/failed semantics
        }

        self::assertTrue($handler->hasErrorThatContains('Telegram delivery failed'));
    }

    public function testDoesNotLogOnSuccess(): void
    {
        $handler = new TestHandler();

        $chatter = $this->createMock(ChatterInterface::class);
        $chatter->expects(self::once())->method('send')->with(self::isInstanceOf(ChatMessage::class));

        new TelegramSender($chatter, new Logger('app', [$handler]))->send('hello');

        self::assertSame([], $handler->getRecords());
    }
}
