<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Repository\CategoryRepository;
use App\Response\Category\CategoryResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ObjectMapperInterface $mapper
    ) {
    }

    #[Route('/categories', name: 'api_categories_list', methods: ['GET'])]
    #[OA\Get(description: 'Returns all product categories.', summary: 'List categories')]
    #[OA\Response(response: 200, description: 'List of categories', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: CategoryResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function list(): JsonResponse
    {
        $categories = $this->categoryRepository->findAllOrderedByName();

        $data = array_map(fn($category) => $this->mapper->map($category, CategoryResponse::class), $categories);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }
}
