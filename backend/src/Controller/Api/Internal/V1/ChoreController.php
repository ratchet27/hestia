<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Exception\ApiProblem;
use App\Request\SaveChoreRequest;
use App\Response\Chore\ChoreResponse;
use App\Service\ChoreService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Chores')]
final class ChoreController extends AbstractController
{
    public function __construct(
        private readonly ChoreService $choreService,
        private readonly ObjectMapperInterface $mapper
    ) {
    }

    #[Route('/chores', name: 'api_chores_list', methods: ['GET'])]
    #[OA\Get(description: 'Returns a list of all chores ordered by next due date.', summary: 'List chores')]
    #[OA\Response(response: 200, description: 'List of chores', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: ChoreResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function list(): JsonResponse
    {
        $chores = $this->choreService->listChores();

        $data = array_map(fn($chore) => $this->mapper->map($chore, ChoreResponse::class), $chores);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route(
        '/chores/{uuid}',
        name: 'api_chores_show',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['GET']
    )]
    #[OA\Get(description: 'Returns a single chore by its UUID.', summary: 'Get chore')]
    #[OA\Response(response: 200, description: 'Chore details', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ChoreResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Chore not found', content: new Model(type: ApiProblem::class))]
    public function show(Uuid $uuid): JsonResponse
    {
        $chore = $this->choreService->getChore($uuid);

        return $this->json(['data' => $this->mapper->map($chore, ChoreResponse::class)]);
    }

    #[Route('/chores', name: 'api_chores_create', methods: ['POST'])]
    #[OA\Post(description: 'Creates a new chore.', summary: 'Create chore')]
    #[OA\Response(response: 201, description: 'Chore created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ChoreResponse::class))
    ]))]
    #[OA\Response(response: 400, description: 'Invalid input', content: new Model(type: ApiProblem::class))]
    public function create(
        #[MapRequestPayload]
        SaveChoreRequest $request
    ): JsonResponse {
        $chore = $this->choreService->createChore($request);

        return $this->json([
            'data' => $this->mapper->map($chore, ChoreResponse::class)
        ], Response::HTTP_CREATED);
    }

    #[Route(
        '/chores/{uuid}',
        name: 'api_chores_update',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['PUT']
    )]
    #[OA\Put(description: 'Updates an existing chore.', summary: 'Update chore')]
    #[OA\Response(response: 200, description: 'Chore updated', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ChoreResponse::class))
    ]))]
    #[OA\Response(response: 400, description: 'Invalid input', content: new Model(type: ApiProblem::class))]
    #[OA\Response(response: 404, description: 'Chore not found', content: new Model(type: ApiProblem::class))]
    public function update(
        Uuid $uuid,
        #[MapRequestPayload]
        SaveChoreRequest $request
    ): JsonResponse {
        $chore = $this->choreService->updateChore($uuid, $request);

        return $this->json([
            'data' => $this->mapper->map($chore, ChoreResponse::class)
        ]);
    }

    #[Route(
        '/chores/{uuid}',
        name: 'api_chores_delete',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['DELETE']
    )]
    #[OA\Delete(description: 'Deletes a chore.', summary: 'Delete chore')]
    #[OA\Response(response: 204, description: 'Chore deleted')]
    #[OA\Response(response: 404, description: 'Chore not found', content: new Model(type: ApiProblem::class))]
    public function delete(Uuid $uuid): JsonResponse
    {
        $this->choreService->deleteChore($uuid);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/chores/{uuid}/done',
        name: 'api_chores_done',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['POST']
    )]
    #[OA\Post(description: 'Marks a chore as done and calculates next due date.', summary: 'Mark chore done')]
    #[OA\Response(response: 200, description: 'Chore marked done', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ChoreResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Chore not found', content: new Model(type: ApiProblem::class))]
    public function markDone(Uuid $uuid): JsonResponse
    {
        $chore = $this->choreService->markChoreDone($uuid);

        return $this->json(['data' => $this->mapper->map($chore, ChoreResponse::class)]);
    }
}
