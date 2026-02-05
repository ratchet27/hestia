<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Task;
use App\Enum\TaskPriority;
use App\Factory\TaskFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

// @mago-ignore lint:too-many-methods
class TaskControllerTest extends WebTestCase
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
        $response = $this->apiGet('/tasks');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 0);
    }

    public function testListReturnsActiveTasks(): void
    {
        TaskFactory::createMany(2, ['done' => false]);
        TaskFactory::createOne(['done' => true]);

        $response = $this->apiGet('/tasks', ['status' => 'active']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListReturnsCompletedTasks(): void
    {
        TaskFactory::createMany(2, ['done' => false]);
        TaskFactory::createOne(['done' => true]);

        $response = $this->apiGet('/tasks', ['status' => 'completed']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
    }

    public function testShowReturnsTask(): void
    {
        $task = TaskFactory::createOne(['name' => 'Test Task']);

        $response = $this->apiGet('/tasks/' . $task->getId());
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertArrayHasKey('data', $data);
        static::assertSame('Test Task', $data['data']['name']);
    }

    public function testShowReturnsNotFoundForMissingTask(): void
    {
        $response = $this->apiGet('/tasks/' . Uuid::v7());
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('Task not found', $data['title']);
        static::assertSame('TASK_NOT_FOUND', $data['type']);
    }

    public function testCreateTask(): void
    {
        $response = $this->apiPost('/tasks', [
            'name' => 'Buy milk',
            'due_date' => '2026-02-10',
            'priority' => 'high'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertArrayHasKey('data', $data);
        static::assertSame('Buy milk', $data['data']['name']);
        static::assertSame('high', $data['data']['priority']);
        static::assertFalse($data['data']['done']);

        $this->assertDatabaseHas(Task::class, ['name' => 'Buy milk']);
    }

    public function testCreateTaskWithDefaults(): void
    {
        $response = $this->apiPost('/tasks', [
            'name' => 'Simple task'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('medium', $data['data']['priority']);
        static::assertNull($data['data']['due_date']);
    }

    public function testUpdateTask(): void
    {
        $task = TaskFactory::createOne(['name' => 'Original', 'priority' => TaskPriority::LOW]);

        $response = $this->apiPut('/tasks/' . $task->getId(), [
            'name' => 'Updated',
            'priority' => 'high',
            'due_date' => '2026-03-01'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('Updated', $data['data']['name']);
        static::assertSame('high', $data['data']['priority']);
    }

    public function testDeleteTask(): void
    {
        $task = TaskFactory::createOne();
        $taskId = $task->getId();

        $response = $this->apiDelete('/tasks/' . $taskId);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->assertDatabaseMissing(Task::class, ['id' => $taskId]);
    }

    public function testToggleTaskDone(): void
    {
        $task = TaskFactory::createOne(['done' => false]);

        $response = $this->apiPatch('/tasks/' . $task->getId() . '/done', []);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertTrue($data['data']['done']);
        static::assertNotNull($data['data']['done_at']);

        // Toggle back
        $response = $this->apiPatch('/tasks/' . $task->getId() . '/done', []);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertFalse($data['data']['done']);
        static::assertNull($data['data']['done_at']);
    }
}
