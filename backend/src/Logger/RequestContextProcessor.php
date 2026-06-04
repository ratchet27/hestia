<?php

declare(strict_types = 1);

namespace App\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stamps the current request's path, method, and client IP onto every log
 * record's `extra`, so any line emitted during a request correlates to it.
 *
 * Backed by RequestStack (request-scoped) rather than Monolog's WebProcessor,
 * which binds the $_SERVER superglobal once and goes stale under FrankenPHP's
 * long-lived workers. No-ops outside an HTTP request (CLI / messenger worker).
 */
final readonly class RequestContextProcessor implements ProcessorInterface
{
    public function __construct(
        private RequestStack $requestStack
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        // Main (not current) request: all logs in one HTTP transaction share the
        // same correlating URL even if a sub-request is mid-flight.
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return $record;
        }

        $record->extra['url'] = $request->getPathInfo();
        $record->extra['http_method'] = $request->getMethod();
        $record->extra['ip'] = $request->getClientIp();

        return $record;
    }
}
