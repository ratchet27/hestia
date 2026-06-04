<?php

declare(strict_types = 1);

namespace App\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Redacts secret-shaped tokens from every log record before it is written.
 *
 * The Telegram bot token appears in symfony/http-client's request/response
 * INFO logs (the API puts it in the URL path: /bot<TOKEN>/sendMessage) and in
 * the TELEGRAM_DSN. This masks the token wherever it surfaces — message,
 * context, or extra — so it never lands in stdout or stderr.
 */
final readonly class RedactSecretsProcessor implements ProcessorInterface
{
    /**
     * A Telegram bot token: <bot id digits>:<35+ base64url chars>. The long,
     * specific secret half keeps this from matching ordinary "n:string" text.
     */
    private const string TOKEN_PATTERN = '#\d{6,}:[A-Za-z0-9_-]{30,}#';

    private const string REDACTED = '[REDACTED]';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redact($record->message),
            context: $this->redactArray($record->context),
            extra: $this->redactArray($record->extra)
        );
    }

    private function redact(string $value): string
    {
        return preg_replace(self::TOKEN_PATTERN, self::REDACTED, $value) ?? $value;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->redact($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->redactArray($value);
            }
        }

        return $data;
    }
}
