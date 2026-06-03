<?php

declare(strict_types = 1);

namespace App\Message;

/**
 * Scheduled trigger for the daily expiry summary. Carries no payload —
 * the handler reads current stock when it runs.
 */
final readonly class SendDailyExpirySummary
{
}
