<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\V1;

use App\Repository\CategoryRepository;
use App\Response\Category\CategoryResponse;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    #[Route('/categories', name: 'api_categories_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'List of categories')]
    public function list(): JsonResponse
    {
        $categories = $this->categoryRepository->findAllOrderedByName();

        $data = array_map(
            CategoryResponse::fromEntity(...),
            $categories
        );

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }
}
