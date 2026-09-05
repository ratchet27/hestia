<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Request\SaveLocationRequest;
use App\Response\Location\LocationListItemResponse;
use App\Service\LocationService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Locations')]
final class LocationController extends AbstractController
{
    public function __construct(
        private readonly LocationService $locationService
    ) {
    }

    #[Route('/locations', name: 'api_locations_list', methods: ['GET'])]
    #[OA\Get(description: 'Returns all storage locations with usage counts.', summary: 'List locations')]
    #[OA\Response(response: 200, description: 'List of locations', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: LocationListItemResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function list(): JsonResponse
    {
        $data = $this->locationService->listItems();

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/locations', name: 'api_locations_create', methods: ['POST'])]
    #[OA\Post(summary: 'Create location', description: 'Creates a storage location.')]
    #[OA\RequestBody(required: true, content: new Model(type: SaveLocationRequest::class))]
    #[OA\Response(response: 201, description: 'Location created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: LocationListItemResponse::class))
    ]))]
    #[OA\Response(response: 409, description: 'Name already exists')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function create(#[MapRequestPayload] SaveLocationRequest $request): JsonResponse
    {
        $location = $this->locationService->create($request);

        return $this->json(['data' => $this->locationService->toListItem($location)], Response::HTTP_CREATED);
    }

    #[Route(
        '/locations/{uuid}',
        name: 'api_locations_update',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['PATCH']
    )]
    #[OA\Patch(summary: 'Rename location', description: 'Renames a storage location.')]
    #[OA\RequestBody(required: true, content: new Model(type: SaveLocationRequest::class))]
    #[OA\Response(response: 200, description: 'Location updated', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: LocationListItemResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Location not found')]
    #[OA\Response(response: 409, description: 'Name already exists')]
    public function update(Uuid $uuid, #[MapRequestPayload] SaveLocationRequest $request): JsonResponse
    {
        $location = $this->locationService->update($uuid, $request);

        return $this->json(['data' => $this->locationService->toListItem($location)]);
    }

    #[Route(
        '/locations/{uuid}',
        name: 'api_locations_delete',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['DELETE']
    )]
    #[OA\Delete(summary: 'Delete location', description: 'Deletes a location only when nothing references it.')]
    #[OA\Response(response: 204, description: 'Location deleted')]
    #[OA\Response(response: 404, description: 'Location not found')]
    #[OA\Response(response: 409, description: 'Location is in use')]
    public function delete(Uuid $uuid): JsonResponse
    {
        $this->locationService->delete($uuid);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
