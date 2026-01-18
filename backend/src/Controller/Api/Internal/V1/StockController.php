<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Request\CreateStockMovementRequest;
use App\Response\Stock\StockMovementResponse;
use App\Response\Stock\StockResponse;
use App\Service\StockService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Stock')]
final class StockController extends AbstractController
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly ObjectMapperInterface $objectMapper
    ) {
    }

    #[Route('/api/internal/v1/stocks', name: 'api_internal_v1_stocks_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List stock levels',
        description: 'Returns stock levels with optional filtering by location and low stock status.'
    )]
    #[OA\Parameter(
        name: 'location',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Parameter(name: 'low_stock', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Response(response: 200, description: 'List of stock entries', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: StockResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function list(Request $request): JsonResponse
    {
        $locationId = $request->query->get('location');
        $lowStockOnly = $request->query->getBoolean('low_stock', false);

        $stocks = $this->stockService->listStocks($locationId, $lowStockOnly);

        $data = array_map(fn($stock) => $this->objectMapper->map($stock, StockResponse::class), $stocks);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/api/internal/v1/stocks/movements', name: 'api_internal_v1_stocks_movements_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create stock movement',
        description: 'Creates a stock movement (ADD, REMOVE, or ADJUST) and updates stock quantity.'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(required: ['product_id', 'location_id', 'type', 'quantity'], properties: [
            new OA\Property(property: 'product_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'location_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'type', type: 'string', enum: ['ADD', 'REMOVE', 'ADJUST']),
            new OA\Property(property: 'quantity', type: 'integer', minimum: 1),
            new OA\Property(property: 'notes', type: 'string', nullable: true)
        ])
    )]
    #[OA\Response(
        response: 201,
        description: 'Movement created',
        content: new Model(type: StockMovementResponse::class)
    )]
    #[OA\Response(response: 400, description: 'Validation error or insufficient stock')]
    #[OA\Response(response: 404, description: 'Product or location not found')]
    public function createMovement(
        #[MapRequestPayload]
        CreateStockMovementRequest $request
    ): JsonResponse {
        $movement = $this->stockService->createMovement($request);

        return $this->json(['data' => $this->objectMapper->map(
            $movement,
            StockMovementResponse::class
        )], Response::HTTP_CREATED);
    }
}
