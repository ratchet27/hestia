<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Tests\Factory\UserFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TelegramControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loginAs(UserFactory::createOne());
    }

    public function testStatusReturnsShape(): void
    {
        $response = $this->apiGet('/telegram/status');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertArrayHasKey('configured', $data['data']);
        static::assertIsBool($data['data']['configured']);
        static::assertArrayHasKey('daily_summary_time', $data['data']);
    }
}
