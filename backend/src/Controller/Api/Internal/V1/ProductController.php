<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Exception\ApiProblem;
use App\Request\CreateProductRequest;
use App\Request\UpdateProductRequest;
use App\Response\Product\ProductResponse;
use App\Service\ProductService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Products')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ObjectMapperInterface $mapper
    ) {
    }

    #[Route('/products', name: 'api_products_list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'List of products', content: new Model(type: ProductResponse::class))]
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

        $data = array_map(fn($product) => $this->mapper->map($product, ProductResponse::class), $products);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route(
        '/products/{uuid}',
        name: 'api_products_show',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['GET']
    )]
    #[OA\Response(response: 200, description: 'Product details', content: new Model(type: ProductResponse::class))]
    #[OA\Response(response: 404, description: 'Product not found', content: new Model(type: ApiProblem::class))]
    public function show(Uuid $uuid): JsonResponse
    {
        $product = $this->productService->getProduct($uuid);

        return $this->json([
            'data' => $this->mapper->map($product, ProductResponse::class)
        ]);
    }

    #[Route('/products', name: 'api_products_create', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Product created', content: new Model(type: ProductResponse::class))]
    #[OA\Response(response: 400, description: 'Invalid input', content: new Model(type: ApiProblem::class))]
    public function create(
        #[MapRequestPayload]
        CreateProductRequest $request
    ): JsonResponse {
        $product = $this->productService->createProduct($request);

        return $this->json([
            'data' => $this->mapper->map($product, ProductResponse::class)
        ], Response::HTTP_CREATED);
    }

    #[Route(
        '/products/{uuid}',
        name: 'api_products_update',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['PUT']
    )]
    #[OA\Response(response: 200, description: 'Product updated', content: new Model(type: ProductResponse::class))]
    #[OA\Response(response: 400, description: 'Invalid input', content: new Model(type: ApiProblem::class))]
    #[OA\Response(response: 404, description: 'Product not found', content: new Model(type: ApiProblem::class))]
    public function update(
        Uuid $uuid,
        #[MapRequestPayload]
        UpdateProductRequest $request
    ): JsonResponse {
        $product = $this->productService->updateProduct($uuid, $request);

        return $this->json([
            'data' => $this->mapper->map($product, ProductResponse::class)
        ]);
    }

    #[Route(
        '/products/{uuid}',
        name: 'api_products_delete',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['DELETE']
    )]
    #[OA\Response(response: 204, description: 'Product deleted')]
    #[OA\Response(response: 404, description: 'Product not found', content: new Model(type: ApiProblem::class))]
    public function delete(Uuid $uuid, Request $request): JsonResponse
    {
        if ($request->query->getBoolean('hard', false)) {
            $this->productService->hardDelete($uuid);

            return $this->json(null, Response::HTTP_NO_CONTENT);
        }

        $this->productService->softDelete($uuid);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
