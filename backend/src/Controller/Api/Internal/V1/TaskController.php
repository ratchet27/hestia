<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Exception\ApiProblem;
use App\Request\CreateTaskRequest;
use App\Request\UpdateTaskRequest;
use App\Response\Task\TaskResponse;
use App\Service\TaskService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Tasks')]
final class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly ObjectMapperInterface $mapper
    ) {
    }

    #[Route('/tasks', name: 'api_tasks_list', methods: ['GET'])]
    #[OA\Get(description: 'Returns a list of tasks.', summary: 'List tasks')]
    #[OA\Parameter(
        name: 'status',
        description: 'Filter by status: active, completed, all',
        in: 'query',
        schema: new OA\Schema(type: 'string', default: 'active')
    )]
    #[OA\Response(response: 200, description: 'List of tasks', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: TaskResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->getString('status', 'active');
        $tasks = $this->taskService->listTasks($status);

        $data = array_map(fn($task) => $this->mapper->map($task, TaskResponse::class), $tasks);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/tasks/{uuid}', name: 'api_tasks_show', requirements: ['uuid' => Requirement::UUID_V7], methods: ['GET'])]
    #[OA\Get(description: 'Returns a single task by its UUID.', summary: 'Get task')]
    #[OA\Response(response: 200, description: 'Task details', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: TaskResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Task not found', content: new Model(type: ApiProblem::class))]
    public function show(Uuid $uuid): JsonResponse
    {
        $task = $this->taskService->getTask($uuid);

        return $this->json(['data' => $this->mapper->map($task, TaskResponse::class)]);
    }

    #[Route('/tasks', name: 'api_tasks_create', methods: ['POST'])]
    #[OA\Post(description: 'Creates a new task.', summary: 'Create task')]
    #[OA\Response(response: 201, description: 'Task created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: TaskResponse::class))
    ]))]
    #[OA\Response(response: 400, description: 'Invalid input', content: new Model(type: ApiProblem::class))]
    public function create(
        #[MapRequestPayload]
        CreateTaskRequest $request
    ): JsonResponse {
        $task = $this->taskService->createTask($request);

        return $this->json([
            'data' => $this->mapper->map($task, TaskResponse::class)
        ], Response::HTTP_CREATED);
    }

    #[Route(
        '/tasks/{uuid}',
        name: 'api_tasks_update',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['PUT']
    )]
    #[OA\Put(description: 'Updates an existing task.', summary: 'Update task')]
    #[OA\Response(response: 200, description: 'Task updated', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: TaskResponse::class))
    ]))]
    #[OA\Response(response: 400, description: 'Invalid input', content: new Model(type: ApiProblem::class))]
    #[OA\Response(response: 404, description: 'Task not found', content: new Model(type: ApiProblem::class))]
    public function update(
        Uuid $uuid,
        #[MapRequestPayload]
        UpdateTaskRequest $request
    ): JsonResponse {
        $task = $this->taskService->updateTask($uuid, $request);

        return $this->json([
            'data' => $this->mapper->map($task, TaskResponse::class)
        ]);
    }

    #[Route(
        '/tasks/{uuid}',
        name: 'api_tasks_delete',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['DELETE']
    )]
    #[OA\Delete(description: 'Deletes a task.', summary: 'Delete task')]
    #[OA\Response(response: 204, description: 'Task deleted')]
    #[OA\Response(response: 404, description: 'Task not found', content: new Model(type: ApiProblem::class))]
    public function delete(Uuid $uuid): JsonResponse
    {
        $this->taskService->deleteTask($uuid);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/tasks/{uuid}/done',
        name: 'api_tasks_toggle_done',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['PATCH']
    )]
    #[OA\Patch(description: 'Toggles task done status.', summary: 'Toggle task done')]
    #[OA\Response(response: 200, description: 'Task status toggled', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: TaskResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Task not found', content: new Model(type: ApiProblem::class))]
    public function toggleDone(Uuid $uuid): JsonResponse
    {
        $task = $this->taskService->toggleTaskDone($uuid);

        return $this->json(['data' => $this->mapper->map($task, TaskResponse::class)]);
    }
}
