<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Logger;

use App\Logger\RequestContextProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestContextProcessorTest extends TestCase
{
    public function testAddsCurrentRequestContextToExtra(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/api/internal/v1/tasks', Request::METHOD_POST));

        $record = ( new RequestContextProcessor($stack) )($this->record());

        self::assertSame('/api/internal/v1/tasks', $record->extra['url']);
        self::assertSame('POST', $record->extra['http_method']);
        self::assertSame('127.0.0.1', $record->extra['ip']);
    }

    public function testPreservesExistingExtra(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/api/internal/v1/tasks', Request::METHOD_GET));

        $record = ( new RequestContextProcessor($stack) )($this->record(['uid' => 'abc123']));

        self::assertSame('abc123', $record->extra['uid']);
        self::assertSame('/api/internal/v1/tasks', $record->extra['url']);
    }

    public function testNoOpWithoutRequest(): void
    {
        $record = ( new RequestContextProcessor(new RequestStack()) )($this->record());

        self::assertArrayNotHasKey('url', $record->extra);
        self::assertArrayNotHasKey('http_method', $record->extra);
        self::assertArrayNotHasKey('ip', $record->extra);
    }

    /** @param array<string, mixed> $extra */
    private function record(array $extra = []): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable('2026-06-04 08:30:00'), 'app', Level::Info, 'test', [], $extra);
    }
}
