<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service\Telegram;

use App\Service\Telegram\TelegramSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

final class TelegramSenderTest extends TestCase
{
    public function testSendsChatMessageWithGivenText(): void
    {
        $chatter = $this->createMock(ChatterInterface::class);
        $chatter
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static fn(ChatMessage $m): bool => $m->getSubject() === 'hello'));

        new TelegramSender($chatter)->send('hello');
    }
}
