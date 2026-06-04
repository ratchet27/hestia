<?php

declare(strict_types = 1);

namespace App\Response\Telegram;

final readonly class TelegramTestResultResponse
{
    public function __construct(
        public bool $ok,
        public ?string $error = null
    ) {
    }
}
