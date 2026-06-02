<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Security;

use App\Factory\UserFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ProtectedRouteTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testTasksRequireAuthentication(): void
    {
        $response = $this->apiGet('/tasks');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testTasksAccessibleWhenAuthenticated(): void
    {
        $this->loginAs(UserFactory::createOne());

        $response = $this->apiGet('/tasks');

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
