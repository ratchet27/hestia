<?php

declare(strict_types = 1);

namespace App\Response\Telegram;

final readonly class TelegramStatusResponse
{
    public function __construct(
        public bool $configured,
        public string $dailySummaryTime
    ) {
    }
}
