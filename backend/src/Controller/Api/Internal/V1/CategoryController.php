<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Entity\Category;
use App\Entity\Product;
use App\Exception\Category\CategoryInUseException;
use App\Exception\Category\CategoryNameTakenException;
use App\Exception\Category\CategoryNotFoundException;
use App\Repository\CategoryRepository;
use App\Request\CreateCategoryRequest;
use App\Request\UpdateCategoryRequest;
use App\Response\Category\CategoryListItemResponse;
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

#[OA\Tag(name: 'Categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    #[Route('/categories', name: 'api_categories_list', methods: ['GET'])]
    #[OA\Get(description: 'Returns all product categories with usage counts.', summary: 'List categories')]
    #[OA\Response(response: 200, description: 'List of categories', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: CategoryListItemResponse::class))
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
        $data = array_map($this->toResponse(...), $categories);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/categories', name: 'api_categories_create', methods: ['POST'])]
    #[OA\Post(summary: 'Create category', description: 'Creates a product category.')]
    #[OA\RequestBody(required: true, content: new Model(type: CreateCategoryRequest::class))]
    #[OA\Response(response: 201, description: 'Category created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: CategoryListItemResponse::class))
    ]))]
    #[OA\Response(response: 409, description: 'Name already exists')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function create(#[MapRequestPayload] CreateCategoryRequest $request): JsonResponse
    {
        $this->assertNameAvailable($request->name);

        $category = new Category();
        $category->setName($request->name);

        $this->em->persist($category);
        $this->em->flush();

        return $this->json(['data' => $this->toResponse($category)], Response::HTTP_CREATED);
    }

    #[Route(
        '/categories/{uuid}',
        name: 'api_categories_update',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['PATCH']
    )]
    #[OA\Patch(summary: 'Rename category', description: 'Renames a product category.')]
    #[OA\RequestBody(required: true, content: new Model(type: UpdateCategoryRequest::class))]
    #[OA\Response(response: 200, description: 'Category updated', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: CategoryListItemResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Category not found')]
    #[OA\Response(response: 409, description: 'Name already exists')]
    public function update(Uuid $uuid, #[MapRequestPayload] UpdateCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryRepository->find($uuid) ?? throw new CategoryNotFoundException($uuid);

        if ($request->name !== $category->getName()) {
            $this->assertNameAvailable($request->name);
            $category->setName($request->name);
            $this->em->flush();
        }

        return $this->json(['data' => $this->toResponse($category)]);
    }

    #[Route(
        '/categories/{uuid}',
        name: 'api_categories_delete',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['DELETE']
    )]
    #[OA\Delete(summary: 'Delete category', description: 'Deletes a category only when no products reference it.')]
    #[OA\Response(response: 204, description: 'Category deleted')]
    #[OA\Response(response: 404, description: 'Category not found')]
    #[OA\Response(response: 409, description: 'Category is in use')]
    public function delete(Uuid $uuid): JsonResponse
    {
        $category = $this->categoryRepository->find($uuid) ?? throw new CategoryNotFoundException($uuid);

        $usage = $this->usageCount($category);
        if ($usage > 0) {
            throw new CategoryInUseException($usage);
        }

        $this->em->remove($category);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function toResponse(Category $category): CategoryListItemResponse
    {
        return new CategoryListItemResponse($category->getId(), $category->getName(), $this->usageCount($category));
    }

    private function usageCount(Category $category): int
    {
        return $this->em->getRepository(Product::class)->count(['category' => $category]);
    }

    private function assertNameAvailable(string $name): void
    {
        if ($this->categoryRepository->findOneBy(['name' => $name]) !== null) {
            throw new CategoryNameTakenException($name);
        }
    }
}
