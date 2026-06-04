<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Logger;

use App\Logger\RedactSecretsProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class RedactSecretsProcessorTest extends TestCase
{
    // A realistic-shaped (fake) Telegram bot token: <digits>:<35 base64url chars>.
    // @mago-ignore lint:no-literal-password
    private const string FAKE_TOKEN = '1234567890:ABCdefGHIjklMNOpqrSTUvwxYZ0123456789';

    /**
     * @param array<array-key, mixed> $context
     * @param array<array-key, mixed> $extra
     */
    private function record(string $message, array $context = [], array $extra = []): LogRecord
    {
        return new LogRecord(
            new \DateTimeImmutable('2026-06-04 08:30:00'),
            'http_client',
            Level::Info,
            $message,
            $context,
            $extra
        );
    }

    public function testRedactsTokenInMessage(): void
    {
        $url = 'https://api.telegram.org/bot' . self::FAKE_TOKEN . '/sendMessage';

        $result = ( new RedactSecretsProcessor() )($this->record('Request: "POST ' . $url . '"'));

        self::assertStringNotContainsString(self::FAKE_TOKEN, $result->message);
        self::assertStringContainsString('https://api.telegram.org/bot[REDACTED]/sendMessage', $result->message);
    }

    public function testRedactsTokenInNestedContext(): void
    {
        $result = ( new RedactSecretsProcessor() )($this->record('Response', [
            'http_code' => 200,
            'url' => 'https://api.telegram.org/bot' . self::FAKE_TOKEN . '/sendMessage'
        ]));

        self::assertStringNotContainsString(self::FAKE_TOKEN, (string) json_encode($result->context));
        self::assertSame(200, $result->context['http_code']);
    }

    public function testRedactsTokenInTelegramDsn(): void
    {
        $result = ( new RedactSecretsProcessor() )($this->record(
            'DSN telegram://' . self::FAKE_TOKEN . '@default?channel=-100123'
        ));

        self::assertStringNotContainsString(self::FAKE_TOKEN, $result->message);
        self::assertStringContainsString('telegram://[REDACTED]@default', $result->message);
    }

    public function testLeavesUnrelatedMessagesUntouched(): void
    {
        $message = 'User "alice" created.';

        $result = ( new RedactSecretsProcessor() )($this->record($message));

        self::assertSame($message, $result->message);
    }
}
