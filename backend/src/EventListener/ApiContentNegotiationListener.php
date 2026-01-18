<?php

declare(strict_types = 1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final readonly class ApiContentNegotiationListener
{
    private const array METHODS_WITH_BODY = ['POST', 'PUT', 'PATCH'];

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Skip content negotiation for API documentation endpoints
        if (str_starts_with($path, '/api/doc')) {
            return;
        }

        $accept = $request->headers->get('Accept') ?? '*/*';
        if (!$this->acceptsJson($accept)) {
            throw new NotAcceptableHttpException(
                'This API only supports JSON responses. Set Accept header to application/json or */*.'
            );
        }

        if (in_array($request->getMethod(), self::METHODS_WITH_BODY, true) && $request->getContent() !== '') {
            $contentType = $request->headers->get('Content-Type') ?? '';
            if (!$this->isJsonContentType($contentType)) {
                throw new UnsupportedMediaTypeHttpException(
                    'This API only accepts JSON requests. Set Content-Type header to application/json.'
                );
            }
        }
    }

    private function acceptsJson(string $accept): bool
    {
        // Match: application/json, application/*+json, */*, application/*
        return (bool) preg_match('#^(\*/\*|application/(\*|json|[a-z0-9.+-]+\+json))#i', $accept);
    }

    private function isJsonContentType(string $contentType): bool
    {
        // Match: application/json or application/*+json, with optional parameters like charset
        return (bool) preg_match('#^application/(json|[a-z0-9.+-]+\+json)(;|$)#i', $contentType);
    }
}
