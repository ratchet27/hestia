<?php

declare(strict_types = 1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\RequestLogListener;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RequestLogListenerTest extends TestCase
{
    public function testLogsApiRequestWithContext(): void
    {
        $handler = new TestHandler();

        $request = Request::create('/api/internal/v1/tasks', \Symfony\Component\HttpFoundation\Request::METHOD_GET);
        $request->server->set('REQUEST_TIME_FLOAT', microtime(true) - 0.05);

        ( new RequestLogListener(new Logger('http', [$handler])) )($this->event(
            $request,
            new Response('', \Symfony\Component\HttpFoundation\Response::HTTP_OK)
        ));

        self::assertTrue($handler->hasInfoThatContains('request handled'));

        [$record] = $handler->getRecords();
        self::assertSame('GET', $record->context['method']);
        self::assertSame('/api/internal/v1/tasks', $record->context['path']);
        self::assertSame(200, $record->context['status']);
        self::assertIsInt($record->context['duration_ms']);
        self::assertGreaterThanOrEqual(0, $record->context['duration_ms']);
    }

    public function testIgnoresNonApiRequest(): void
    {
        $handler = new TestHandler();

        ( new RequestLogListener(new Logger('http', [$handler])) )($this->event(
            Request::create('/_wdt/abc', \Symfony\Component\HttpFoundation\Request::METHOD_GET),
            new Response()
        ));

        self::assertSame([], $handler->getRecords());
    }

    public function testLogsNullDurationWhenStartTimeAbsent(): void
    {
        $handler = new TestHandler();

        $request = Request::create('/api/internal/v1/tasks', Request::METHOD_GET);
        $request->server->remove('REQUEST_TIME_FLOAT');

        ( new RequestLogListener(new Logger('http', [$handler])) )($this->event(
            $request,
            new Response('', Response::HTTP_OK)
        ));

        [$record] = $handler->getRecords();
        self::assertNull($record->context['duration_ms']);
    }

    private function event(Request $request, Response $response): TerminateEvent
    {
        return new TerminateEvent($this->createStub(HttpKernelInterface::class), $request, $response);
    }
}
