<?php

declare(strict_types = 1);

namespace App\Service\Telegram;

use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

final readonly class TelegramSender
{
    public function __construct(
        private ChatterInterface $chatter
    ) {
    }

    public function send(string $message): void
    {
        $chatMessage = new ChatMessage($message, new TelegramOptions()->parseMode('HTML'));
        $chatMessage->transport('telegram');

        // Exceptions propagate so Messenger's async retry (3x) + failed transport handle delivery.
        $this->chatter->send($chatMessage);
    }
}
