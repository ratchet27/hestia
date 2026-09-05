<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Controller\Api\Internal\V1;

use App\Controller\Api\Internal\V1\TelegramController;
use App\Service\Telegram\TelegramSender;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TelegramControllerTest extends TestCase
{
    // A DSN with a fake bot token: the assertions check it never reaches the client.
    // @mago-ignore lint:no-literal-password
    private const string SECRET = 'telegram://123456:SECRET-TOKEN@default?channel=42';

    public function testTransportFailureIsReportedAsAFixedCode(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getInfo')->willReturn(null);
        $body = $this->callTest(new TransportException('Unable to post to ' . self::SECRET, $response));

        static::assertFalse($body['data']['ok']);
        static::assertSame('transport_error', $body['data']['error']);
        static::assertStringNotContainsString('SECRET-TOKEN', json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function testAnyOtherFailureIsReportedAsSendFailed(): void
    {
        $body = $this->callTest(new \RuntimeException('internal detail with ' . self::SECRET));

        static::assertSame('send_failed', $body['data']['error']);
        static::assertStringNotContainsString('SECRET-TOKEN', json_encode($body, JSON_THROW_ON_ERROR));
    }

    /** @return array{data: array{ok: bool, error: ?string}} */
    private function callTest(\Throwable $failure): array
    {
        // TelegramSender is final; drive the failure through the chatter it wraps.
        $chatter = $this->createMock(ChatterInterface::class);
        $chatter->method('send')->willThrowException($failure);
        $sender = new TelegramSender($chatter, new NullLogger());

        $controller = new TelegramController(self::SECRET, '09:00', $sender);
        $controller->setContainer(new Container());

        // @mago-ignore analysis:mixed-return-statement
        return json_decode((string) $controller->test()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
