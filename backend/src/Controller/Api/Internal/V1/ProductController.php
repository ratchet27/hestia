<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\V1;

use App\DTO\Request\CreateProductRequest;
use App\DTO\Request\UpdateProductRequest;
use App\DTO\Response\ProductResponse;
use App\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    #[Route('/products', name: 'api_products_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->query->has('name')) {
            $filters['name'] = $request->query->getString('name');
        }

        if ($request->query->has('category_id')) {
            $filters['category_id'] = $request->query->getString('category_id');
        }

        if ($request->query->has('active')) {
            $filters['active'] = $request->query->getBoolean('active');
        }

        $products = $this->productService->listProducts($filters);

        $data = array_map(
            static fn($product) => ProductResponse::fromEntity($product)->toArray(),
            $products
        );

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }

    #[Route('/products/{id}', name: 'api_products_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException('Invalid UUID format');
        }

        $product = $this->productService->getProduct($uuid);

        return $this->json([
            'data' => ProductResponse::fromEntity($product, includeBarcodes: true)->toArray(),
        ]);
    }

    #[Route('/products', name: 'api_products_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateProductRequest $request,
    ): JsonResponse {
        $product = $this->productService->createProduct($request->toArray());

        return $this->json(
            ['data' => ProductResponse::fromEntity($product, includeBarcodes: true)->toArray()],
            Response::HTTP_CREATED
        );
    }

    #[Route('/products/{id}', name: 'api_products_update', methods: ['PATCH'])]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateProductRequest $request,
    ): JsonResponse {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException('Invalid UUID format');
        }

        $product = $this->productService->updateProduct($uuid, $request->toArray());

        return $this->json([
            'data' => ProductResponse::fromEntity($product, includeBarcodes: true)->toArray(),
        ]);
    }

    #[Route('/products/{id}', name: 'api_products_delete', methods: ['DELETE'])]
    public function delete(string $id, Request $request): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException('Invalid UUID format');
        }

        if ($request->query->getBoolean('hard', false)) {
            $this->productService->hardDelete($uuid);
        } else {
            $this->productService->softDelete($uuid);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
