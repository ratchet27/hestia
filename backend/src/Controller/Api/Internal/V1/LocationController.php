<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Repository\LocationRepository;
use App\Response\Location\LocationResponse;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Locations')]
final class LocationController extends AbstractController
{
    public function __construct(
        private readonly LocationRepository $locationRepository
    ) {
    }

    #[Route('/locations', name: 'api_locations_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'List of locations')]
    public function list(): JsonResponse
    {
        $locations = $this->locationRepository->findAllOrderedByName();

        $data = array_map(LocationResponse::fromEntity(...), $locations);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }
}
