<?php

declare(strict_types = 1);

namespace App\Service\Telegram;

use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

final readonly class TelegramSender
{
    public function __construct(
        private ChatterInterface $chatter,
        private LoggerInterface $logger
    ) {
    }

    public function send(string $message): void
    {
        $chatMessage = new ChatMessage($message, new TelegramOptions()->parseMode('HTML'));
        $chatMessage->transport('telegram');

        try {
            $this->chatter->send($chatMessage);
        } catch (\Throwable $throwable) {
            // http_client request/response logs are silenced, so emit an explicit
            // failure line here (Messenger separately logs retry WARNINGs + a final
            // CRITICAL — this ERROR is the immediate per-attempt domain signal).
            // Re-throw so Messenger's async retry (3x) + failed transport still apply.
            $this->logger->error('Telegram delivery failed', [
                'exception' => $throwable,
                'length' => mb_strlen($message)
            ]);

            throw $throwable;
        }
    }
}
