<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\V1;

use App\Repository\LocationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LocationController extends AbstractController
{
    public function __construct(
        private readonly LocationRepository $locationRepository,
    ) {}

    #[Route('/locations', name: 'api_locations_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $locations = $this->locationRepository->findAllOrderedByName();

        $data = array_map(
            static fn($location) => [
                'id' => (string) $location->getId(),
                'name' => $location->getName(),
            ],
            $locations
        );

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }
}
