<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\V1;

use App\Request\CreateProductRequest;
use App\Request\UpdateProductRequest;
use App\Response\Product\ProductResponse;
use App\Service\ProductService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Products')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    #[Route('/products', name: 'api_products_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'List of products')]
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
            ProductResponse::fromEntity(...),
            $products
        );

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }

    #[Route('/products/{id}', name: 'api_products_show', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Product details')]
    #[OA\Response(response: 404, description: 'Product not found')]
    public function show(string $id): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException('Invalid UUID format');
        }

        $product = $this->productService->getProduct($uuid);

        return $this->json([
            'data' => ProductResponse::fromEntity($product, includeBarcodes: true),
        ]);
    }

    #[Route('/products', name: 'api_products_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Product created')]
    #[OA\Response(response: 400, description: 'Invalid input')]
    public function create(
        #[MapRequestPayload] CreateProductRequest $request,
    ): JsonResponse {
        $product = $this->productService->createProduct($request);

        return $this->json(
            ['data' => ProductResponse::fromEntity($product, includeBarcodes: true)],
            Response::HTTP_CREATED
        );
    }

    #[Route('/products/{id}', name: 'api_products_update', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Product updated')]
    #[OA\Response(response: 400, description: 'Invalid input')]
    #[OA\Response(response: 404, description: 'Product not found')]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateProductRequest $request,
    ): JsonResponse {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException('Invalid UUID format');
        }

        $product = $this->productService->updateProduct($uuid, $request);

        return $this->json([
            'data' => ProductResponse::fromEntity($product, includeBarcodes: true),
        ]);
    }

    #[Route('/products/{id}', name: 'api_products_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Product deleted')]
    #[OA\Response(response: 404, description: 'Product not found')]
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
