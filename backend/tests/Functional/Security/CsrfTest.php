<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Security;

use App\Factory\UserFactory;
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
        $this->client->request('POST', '/api/internal/v1/tasks', [], [], self::JSON_HEADERS, json_encode([
            'name' => 'x'
        ]));

        static::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testCsrfEndpointSetsCookieAndRequestSucceeds(): void
    {
        $this->client->request('GET', '/api/internal/v1/auth/csrf');
        $cookie = $this->client->getCookieJar()->get('XSRF-TOKEN');
        static::assertNotNull($cookie);

        $headers = self::JSON_HEADERS + ['HTTP_X_CSRF_TOKEN' => $cookie->getValue()];
        $this->client->request('POST', '/api/internal/v1/tasks', [], [], $headers, json_encode(['name' => 'Buy milk']));

        static::assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
    }
}
