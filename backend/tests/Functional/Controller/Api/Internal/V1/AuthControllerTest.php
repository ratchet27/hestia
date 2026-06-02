<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Factory\UserFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AuthControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testLoginSucceedsWithValidCredentials(): void
    {
        // @mago-ignore lint:no-literal-password
        UserFactory::createOne(['username' => 'pavel', 'name' => 'Pavel', 'plainPassword' => 'secret123']);

        // @mago-ignore lint:no-literal-password
        $response = $this->apiPost('/auth/login', ['username' => 'pavel', 'password' => 'secret123']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('pavel', $data['data']['username']);
        static::assertSame('Pavel', $data['data']['name']);
        static::assertContains('ROLE_USER', $data['data']['roles']);
    }

    public function testLoginFailsWithBadPassword(): void
    {
        // @mago-ignore lint:no-literal-password
        UserFactory::createOne(['username' => 'pavel', 'plainPassword' => 'secret123']);

        // @mago-ignore lint:no-literal-password
        $response = $this->apiPost('/auth/login', ['username' => 'pavel', 'password' => 'wrong']);

        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testMeReturns401WhenAnonymous(): void
    {
        $response = $this->apiGet('/auth/me');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testMeReturnsCurrentUserWhenAuthenticated(): void
    {
        $user = UserFactory::createOne(['username' => 'anna', 'name' => 'Anna']);
        $this->loginAs($user);

        $response = $this->apiGet('/auth/me');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('anna', $data['data']['username']);
    }

    public function testLogoutClearsSession(): void
    {
        $user = UserFactory::createOne(['username' => 'pavel']);
        $this->loginAs($user);

        $this->apiPost('/auth/logout', []);
        $response = $this->apiGet('/auth/me');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
