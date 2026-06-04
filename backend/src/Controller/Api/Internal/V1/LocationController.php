<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Exception\Location\LocationInUseException;
use App\Exception\Location\LocationNameTakenException;
use App\Exception\Location\LocationNotFoundException;
use App\Repository\LocationRepository;
use App\Request\CreateLocationRequest;
use App\Request\UpdateLocationRequest;
use App\Response\Location\LocationListItemResponse;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly LocationRepository $locationRepository,
        private readonly EntityManagerInterface $em
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
        $locations = $this->locationRepository->findAllOrderedByName();
        $data = array_map($this->toResponse(...), $locations);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/locations', name: 'api_locations_create', methods: ['POST'])]
    #[OA\Post(summary: 'Create location', description: 'Creates a storage location.')]
    #[OA\RequestBody(required: true, content: new Model(type: CreateLocationRequest::class))]
    #[OA\Response(response: 201, description: 'Location created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: LocationListItemResponse::class))
    ]))]
    #[OA\Response(response: 409, description: 'Name already exists')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function create(#[MapRequestPayload] CreateLocationRequest $request): JsonResponse
    {
        $this->assertNameAvailable($request->name);

        $location = new Location();
        $location->setName($request->name);

        $this->em->persist($location);
        $this->em->flush();

        return $this->json(['data' => $this->toResponse($location)], Response::HTTP_CREATED);
    }

    #[Route(
        '/locations/{uuid}',
        name: 'api_locations_update',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['PATCH']
    )]
    #[OA\Patch(summary: 'Rename location', description: 'Renames a storage location.')]
    #[OA\RequestBody(required: true, content: new Model(type: UpdateLocationRequest::class))]
    #[OA\Response(response: 200, description: 'Location updated', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: LocationListItemResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Location not found')]
    #[OA\Response(response: 409, description: 'Name already exists')]
    public function update(Uuid $uuid, #[MapRequestPayload] UpdateLocationRequest $request): JsonResponse
    {
        $location = $this->locationRepository->find($uuid) ?? throw new LocationNotFoundException($uuid);

        if ($request->name !== $location->getName()) {
            $this->assertNameAvailable($request->name);
            $location->setName($request->name);
            $this->em->flush();
        }

        return $this->json(['data' => $this->toResponse($location)]);
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
        $location = $this->locationRepository->find($uuid) ?? throw new LocationNotFoundException($uuid);

        $usage = $this->usageCount($location);
        if ($usage > 0) {
            throw new LocationInUseException($usage);
        }

        $this->em->remove($location);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function toResponse(Location $location): LocationListItemResponse
    {
        return new LocationListItemResponse($location->getId(), $location->getName(), $this->usageCount($location));
    }

    private function usageCount(Location $location): int
    {
        return (
            $this->em->getRepository(Product::class)->count(['defaultLocation' => $location])
            + $this->em->getRepository(StockEntry::class)->count(['location' => $location])
        );
    }

    private function assertNameAvailable(string $name): void
    {
        if ($this->locationRepository->findOneBy(['name' => $name]) !== null) {
            throw new LocationNameTakenException($name);
        }
    }
}
