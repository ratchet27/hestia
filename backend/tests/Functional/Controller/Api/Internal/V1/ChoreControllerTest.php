<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Chore;
use App\Enum\ScheduleType;
use App\Factory\ChoreFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ChoreControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testListReturnsEmptyWhenNoData(): void
    {
        $response = $this->apiGet('/chores');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 0);
    }

    public function testListReturnsChores(): void
    {
        ChoreFactory::createMany(3);

        $response = $this->apiGet('/chores');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 3);
    }

    public function testShowReturnsChore(): void
    {
        $chore = ChoreFactory::createOne(['name' => 'Test Chore']);

        $response = $this->apiGet('/chores/' . $chore->getId());
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertArrayHasKey('data', $data);
        static::assertSame('Test Chore', $data['data']['name']);
    }

    public function testShowReturnsNotFoundForMissingChore(): void
    {
        $response = $this->apiGet('/chores/' . Uuid::v7());
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('Chore not found', $data['title']);
        static::assertSame('CHORE_NOT_FOUND', $data['type']);
    }

    public function testCreateChore(): void
    {
        $response = $this->apiPost('/chores', [
            'name' => 'Clean kitchen',
            'schedule_type' => 'interval',
            'schedule_value' => 7,
            'assignee' => 'Pavel'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertArrayHasKey('data', $data);
        static::assertSame('Clean kitchen', $data['data']['name']);
        static::assertSame('interval', $data['data']['schedule_type']);
        static::assertSame(7, $data['data']['schedule_value']);
        static::assertSame('Pavel', $data['data']['assignee']);

        $this->assertDatabaseHas(Chore::class, ['name' => 'Clean kitchen']);
    }

    public function testCreateChoreValidation(): void
    {
        $response = $this->apiPost('/chores', [
            'name' => '',
            'schedule_type' => 'invalid',
            'schedule_value' => 0
        ]);
        static::assertErrorResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateChore(): void
    {
        $chore = ChoreFactory::createOne([
            'name' => 'Original',
            'scheduleType' => ScheduleType::INTERVAL,
            'scheduleValue' => 7
        ]);

        $response = $this->apiPut('/chores/' . $chore->getId(), [
            'name' => 'Updated',
            'schedule_type' => 'fixed_weekly',
            'schedule_value' => 1,
            'assignee' => null
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('Updated', $data['data']['name']);
        static::assertSame('fixed_weekly', $data['data']['schedule_type']);
    }

    public function testDeleteChore(): void
    {
        $chore = ChoreFactory::createOne();
        $choreId = $chore->getId();

        $response = $this->apiDelete('/chores/' . $choreId);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->assertDatabaseMissing(Chore::class, ['id' => $choreId]);
    }

    public function testMarkChoreDone(): void
    {
        // Set a specific next_due that won't collide with today + 7 days
        $specificNextDue = new \DateTimeImmutable('+30 days');
        $chore = ChoreFactory::createOne([
            'scheduleType' => ScheduleType::INTERVAL,
            'scheduleValue' => 7,
            'nextDueAt' => $specificNextDue
        ]);

        $response = $this->apiPost('/chores/' . $chore->getId() . '/done', []);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertNotNull($data['data']['last_done_at']);
        // After marking done, next_due should be ~7 days from now, not +30 days
        static::assertNotEquals(
            $specificNextDue->format('Y-m-d'),
            substr((string) $data['data']['next_due_at'], 0, 10)
        );
    }
}
