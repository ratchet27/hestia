<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\V1;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    #[Route('/categories', name: 'api_categories_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $categories = $this->categoryRepository->findAllOrderedByName();

        $data = array_map(
            static fn($category) => [
                'id' => (string) $category->getId(),
                'name' => $category->getName(),
            ],
            $categories
        );

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }
}
