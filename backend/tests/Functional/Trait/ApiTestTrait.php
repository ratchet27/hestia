<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Trait;

use Symfony\Component\HttpFoundation\Response;

trait ApiTestTrait
{
    private const string API_PREFIX = '/api/internal/v1';

    /** @var array<string, string> */
    private const array JSON_HEADERS = [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json'
    ];

    /** @param array<string, mixed> $parameters */
    protected function apiGet(string $uri, array $parameters = []): Response
    {
        $this->client->request('GET', self::API_PREFIX . $uri, $parameters, [], self::JSON_HEADERS);

        return $this->client->getResponse();
    }

    /** @param array<string, mixed> $data */
    protected function apiPost(string $uri, array $data): Response
    {
        $this->client->request(
            'POST',
            self::API_PREFIX . $uri,
            [],
            [],
            self::JSON_HEADERS,
            json_encode($data, JSON_THROW_ON_ERROR)
        );

        return $this->client->getResponse();
    }

    /** @param array<string, mixed> $data */
    protected function apiPatch(string $uri, array $data): Response
    {
        $this->client->request(
            'PATCH',
            self::API_PREFIX . $uri,
            [],
            [],
            self::JSON_HEADERS,
            json_encode($data, JSON_THROW_ON_ERROR)
        );

        return $this->client->getResponse();
    }

    /** @param array<string, mixed> $parameters */
    protected function apiDelete(string $uri, array $parameters = []): Response
    {
        $queryString = $parameters ? '?' . http_build_query($parameters) : '';
        $this->client->request('DELETE', self::API_PREFIX . $uri . $queryString, [], [], self::JSON_HEADERS);

        return $this->client->getResponse();
    }

    /** @return array<string, mixed> */
    protected function assertJsonResponse(Response $response, int $expectedStatusCode): array
    {
        static::assertSame($expectedStatusCode, $response->getStatusCode());
        static::assertSame('application/json', $response->headers->get('Content-Type'));

        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $data */
    protected function assertListResponse(array $data, int $expectedTotal): void
    {
        static::assertArrayHasKey('data', $data);
        static::assertArrayHasKey('meta', $data);
        static::assertArrayHasKey('total', $data['meta']);
        static::assertSame($expectedTotal, $data['meta']['total']);
        static::assertCount($expectedTotal, $data['data']);
    }
}
