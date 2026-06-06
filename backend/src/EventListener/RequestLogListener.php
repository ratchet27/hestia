<?php

declare(strict_types = 1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Emits one access line per API request on the "http" channel — the meaningful
 * replacement for the framework's pre-handler "Matched route" log. Carries the
 * outcome (status) and timing, correlated to the request via the uid processor.
 */
#[AsEventListener(event: KernelEvents::TERMINATE)]
final readonly class RequestLogListener
{
    public function __construct(
        private LoggerInterface $httpLogger
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // @mago-ignore analysis:mixed-assignment -- Symfony ServerBag::get() is mixed; narrowed by is_numeric() below
        $startedAt = $request->server->get('REQUEST_TIME_FLOAT');
        $durationMs = is_numeric($startedAt)
            ? max(0, (int) round(( microtime(true) - (float) $startedAt ) * 1000))
            : null;

        $this->httpLogger->info('request handled', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'status' => $event->getResponse()->getStatusCode(),
            'duration_ms' => $durationMs
        ]);
    }
}
