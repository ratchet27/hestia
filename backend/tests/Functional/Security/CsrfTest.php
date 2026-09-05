<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Security;

use App\Tests\Factory\UserFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CsrfTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loginAs(UserFactory::createOne());
    }

    public function testUnsafeRequestWithoutCsrfTokenIsRejected(): void
    {
        $this->client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_POST,
            '/api/internal/v1/tasks',
            [],
            [],
            self::JSON_HEADERS,
            json_encode([
                'name' => 'x'
            ])
        );

        static::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testCsrfEndpointSetsCookieAndRequestSucceeds(): void
    {
        $this->client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/api/internal/v1/auth/csrf');
        $cookie = $this->client->getCookieJar()->get('XSRF-TOKEN');
        static::assertNotNull($cookie);

        $headers = self::JSON_HEADERS + ['HTTP_X_CSRF_TOKEN' => $cookie->getValue()];
        $this->client->request(
            \Symfony\Component\HttpFoundation\Request::METHOD_POST,
            '/api/internal/v1/tasks',
            [],
            [],
            $headers,
            json_encode(['name' => 'Buy milk'])
        );

        static::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
    }

    public function testLoginRotatesTheCsrfToken(): void
    {
        // @mago-ignore lint:no-literal-password
        UserFactory::createOne(['username' => 'rotate', 'plainPassword' => 'secret123']);
        $this->client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/api/internal/v1/auth/csrf');
        $before = $this->client->getCookieJar()->get('XSRF-TOKEN')?->getValue();
        static::assertNotNull($before);

        // @mago-ignore lint:no-literal-password
        $response = $this->apiPost('/auth/login', ['username' => 'rotate', 'password' => 'secret123']);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $after = $this->client->getCookieJar()->get('XSRF-TOKEN')?->getValue();
        static::assertNotNull($after);
        static::assertNotSame($before, $after, 'a token planted before login must not survive it');
    }

    public function testLogoutRotatesTheCsrfToken(): void
    {
        $this->client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/api/internal/v1/auth/csrf');
        $before = $this->client->getCookieJar()->get('XSRF-TOKEN')?->getValue();

        $this->apiPost('/auth/logout', []);

        $after = $this->client->getCookieJar()->get('XSRF-TOKEN')?->getValue();
        static::assertNotNull($after);
        static::assertNotSame($before, $after, 'a token from the old session must not survive logout');
    }

    public function testUnrelatedResponseKeepsTheExistingToken(): void
    {
        $this->client->request(\Symfony\Component\HttpFoundation\Request::METHOD_GET, '/api/internal/v1/auth/csrf');
        $before = $this->client->getCookieJar()->get('XSRF-TOKEN')?->getValue();

        $this->apiGet('/products');

        static::assertSame($before, $this->client->getCookieJar()->get('XSRF-TOKEN')?->getValue());
    }
}
