<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Request\AddStockRequest;
use App\Request\ConsumeStockRequest;
use App\Request\ExpiringStockQuery;
use App\Request\StockEntriesQuery;
use App\Request\UpdateStockEntryRequest;
use App\Response\Stock\AddedStockEntryResponse;
use App\Response\Stock\ConsumeResultResponse;
use App\Response\Stock\ExpiringEntryResponse;
use App\Response\Stock\ProductSummaryResponse;
use App\Response\Stock\StockEntryResponse;
use App\Service\StockEntryService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Stock')]
final class StockController extends AbstractController
{
    public function __construct(
        private readonly StockEntryService $stockEntryService
    ) {
    }

    #[Route('/stocks', name: 'api_internal_v1_stocks_summary', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get stock summary',
        description: 'Returns stock summary aggregated by product with location breakdown.'
    )]
    #[OA\Parameter(name: 'low_stock', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Response(response: 200, description: 'Stock summary', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: ProductSummaryResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function summary(Request $request): JsonResponse
    {
        $lowStockOnly = $request->query->getBoolean('low_stock', false);
        $data = $this->stockEntryService->getStockSummary($lowStockOnly);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/stocks/entries', name: 'api_internal_v1_stocks_entries_list', methods: ['GET'])]
    #[OA\Get(summary: 'List stock entries', description: 'Returns individual stock entries with optional filtering.')]
    #[OA\Parameter(
        name: 'location',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Parameter(
        name: 'product',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(response: 200, description: 'List of entries', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: StockEntryResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function listEntries(
        #[MapQueryString]
        StockEntriesQuery $query = new StockEntriesQuery()
    ): JsonResponse {
        $data = $this->stockEntryService->getEntries(
            locationId: $query->location !== null ? Uuid::fromString($query->location) : null,
            productId: $query->product !== null ? Uuid::fromString($query->product) : null
        );

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route(
        '/stocks/entries/{uuid}',
        name: 'api_internal_v1_stocks_entries_show',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['GET']
    )]
    #[OA\Get(summary: 'Get stock entry', description: 'Returns a single stock entry by ID.')]
    #[OA\Response(response: 200, description: 'Stock entry', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: StockEntryResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Entry not found')]
    public function showEntry(Uuid $uuid): JsonResponse
    {
        $entry = $this->stockEntryService->getEntry($uuid);

        return $this->json(['data' => $entry]);
    }

    #[Route('/stocks/expiring', name: 'api_internal_v1_stocks_expiring', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get expiring entries',
        description: 'Returns entries expiring within N days, including already expired. Ordered by urgency.'
    )]
    #[OA\Response(response: 200, description: 'Expiring entries', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: ExpiringEntryResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function expiring(
        #[MapQueryString]
        ExpiringStockQuery $query = new ExpiringStockQuery()
    ): JsonResponse {
        $data = $this->stockEntryService->getExpiringEntries($query->days);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/stocks/add', name: 'api_internal_v1_stocks_add', methods: ['POST'])]
    #[OA\Post(summary: 'Add stock', description: 'Creates N stock entries (one per unit).')]
    #[OA\RequestBody(required: true, content: new Model(type: AddStockRequest::class))]
    #[OA\Response(response: 201, description: 'Entries created', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            properties: [
                new OA\Property(property: 'created', type: 'integer'),
                new OA\Property(
                    property: 'entries',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: AddedStockEntryResponse::class))
                )
            ],
            type: 'object'
        )
    ]))]
    #[OA\Response(response: 404, description: 'Product or location not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function add(#[MapRequestPayload] AddStockRequest $request): JsonResponse
    {
        $entries = $this->stockEntryService->addStock($request);

        return $this->json([
            'data' => [
                'created' => count($entries),
                'entries' => array_map(AddedStockEntryResponse::fromEntity(...), $entries)
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/stocks/consume', name: 'api_internal_v1_stocks_consume', methods: ['POST'])]
    #[OA\Post(summary: 'Consume stock', description: 'Deletes N entries in FIFO order from specified location.')]
    #[OA\RequestBody(required: true, content: new Model(type: ConsumeStockRequest::class))]
    #[OA\Response(response: 200, description: 'Consumption result', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ConsumeResultResponse::class))
    ]))]
    #[OA\Response(response: 400, description: 'Insufficient stock')]
    #[OA\Response(response: 404, description: 'Product or location not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function consume(#[MapRequestPayload] ConsumeStockRequest $request): JsonResponse
    {
        $result = $this->stockEntryService->consumeStock($request);

        return $this->json(['data' => $result]);
    }

    #[Route(
        '/stocks/entries/{uuid}',
        name: 'api_internal_v1_stocks_entries_update',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['PATCH']
    )]
    #[OA\Patch(summary: 'Update stock entry', description: 'Updates entry location and/or best_before.')]
    #[OA\RequestBody(required: true, content: new Model(type: UpdateStockEntryRequest::class))]
    #[OA\Response(response: 200, description: 'Updated entry', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: StockEntryResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Entry or location not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function updateEntry(Uuid $uuid, #[MapRequestPayload] UpdateStockEntryRequest $request): JsonResponse
    {
        $entry = $this->stockEntryService->updateEntry($uuid, $request);

        return $this->json(['data' => $this->stockEntryService->getEntry($entry->getId())]);
    }

    #[Route(
        '/stocks/entries/{uuid}',
        name: 'api_internal_v1_stocks_entries_delete',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['DELETE']
    )]
    #[OA\Delete(summary: 'Delete stock entry', description: 'Removes a single stock entry.')]
    #[OA\Response(response: 204, description: 'Entry deleted')]
    #[OA\Response(response: 404, description: 'Entry not found')]
    public function deleteEntry(Uuid $uuid): JsonResponse
    {
        $this->stockEntryService->deleteEntry($uuid);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
