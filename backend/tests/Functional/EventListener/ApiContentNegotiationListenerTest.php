<?php

declare(strict_types = 1);

namespace App\Tests\Functional\EventListener;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiContentNegotiationListenerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testRejectsTextHtmlAcceptHeader(): void
    {
        $this->client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_GET,
            '/api/internal/v1/categories',
            [],
            [],
            [
                'HTTP_ACCEPT' => 'text/html'
            ]
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_NOT_ACCEPTABLE, $response->getStatusCode());
        static::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        static::assertSame('NOT_ACCEPTABLE', $data['type']);
    }

    public function testRejectsTextPlainContentType(): void
    {
        $this->client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_POST,
            '/api/internal/v1/products',
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'text/plain'
            ],
            'some content'
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode());
        static::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        static::assertSame('UNSUPPORTED_MEDIA_TYPE', $data['type']);
    }

    public function testAcceptsWildcardAndVendorJson(): void
    {
        $this->client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_POST,
            '/api/internal/v1/products',
            [],
            [],
            [
                'HTTP_ACCEPT' => '*/*',
                'CONTENT_TYPE' => 'application/vnd.foo+json; charset=utf-8'
            ],
            json_encode(['name' => 'Test Product'], JSON_THROW_ON_ERROR)
        );

        $response = $this->client->getResponse();

        // Should get through content negotiation (may fail validation or succeed)
        static::assertNotSame(Response::HTTP_NOT_ACCEPTABLE, $response->getStatusCode());
        static::assertNotSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode());
    }

    public function testAcceptsVendorJsonAcceptHeader(): void
    {
        $this->client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_GET,
            '/api/internal/v1/categories',
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/vnd.api+json'
            ]
        );

        $response = $this->client->getResponse();

        static::assertNotSame(Response::HTTP_NOT_ACCEPTABLE, $response->getStatusCode());
    }

    public function testAllowsGetWithoutContentType(): void
    {
        $this->client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_GET,
            '/api/internal/v1/categories',
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json'
            ]
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
