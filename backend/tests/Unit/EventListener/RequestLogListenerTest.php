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

        static::assertTrue($handler->hasInfoThatContains('request handled'));

        [$record] = $handler->getRecords();
        static::assertSame('GET', $record->context['method']);
        static::assertSame('/api/internal/v1/tasks', $record->context['path']);
        static::assertSame(200, $record->context['status']);
        static::assertIsInt($record->context['duration_ms']);
        static::assertGreaterThanOrEqual(0, $record->context['duration_ms']);
    }

    public function testIgnoresNonApiRequest(): void
    {
        $handler = new TestHandler();

        ( new RequestLogListener(new Logger('http', [$handler])) )($this->event(
            Request::create('/_wdt/abc', \Symfony\Component\HttpFoundation\Request::METHOD_GET),
            new Response()
        ));

        static::assertSame([], $handler->getRecords());
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
        static::assertNull($record->context['duration_ms']);
    }

    private function event(Request $request, Response $response): TerminateEvent
    {
        return new TerminateEvent($this->createStub(HttpKernelInterface::class), $request, $response);
    }
}
