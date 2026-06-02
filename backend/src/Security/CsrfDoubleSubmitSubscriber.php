<?php

declare(strict_types = 1);

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class CsrfDoubleSubmitSubscriber implements EventSubscriberInterface
{
    private const string COOKIE_NAME = 'XSRF-TOKEN';
    private const string HEADER_NAME = 'X-CSRF-Token';
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** @return array<string, array{0: string, 1: int}> */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 6],
            KernelEvents::RESPONSE => ['onResponse', 0]
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->isProtected($request)) {
            return;
        }

        $cookie = $request->cookies->get(self::COOKIE_NAME);
        $header = $request->headers->get(self::HEADER_NAME);

        if ($cookie === null || $header === null || !hash_equals($cookie, $header)) {
            $event->setResponse(new JsonResponse(['message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN));
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/internal/v1')) {
            return;
        }

        if ($request->cookies->has(self::COOKIE_NAME)) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $event
            ->getResponse()
            ->headers->setCookie(
                Cookie::create(self::COOKIE_NAME, $token)
                    ->withSecure(true)
                    ->withHttpOnly(false)
                    ->withSameSite(Cookie::SAMESITE_LAX)
                    ->withPath('/')
            );
    }

    private function isProtected(Request $request): bool
    {
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return false;
        }

        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api/internal/v1')) {
            return false;
        }

        return (
            !str_starts_with($path, '/api/internal/v1/auth/login')
            && !str_starts_with($path, '/api/internal/v1/auth/logout')
        );
    }
}
