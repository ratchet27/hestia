<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Request\AddShoppingItemRequest;
use App\Request\UpdateShoppingItemRequest;
use App\Response\ShoppingList\ShoppingItemResponse;
use App\Service\ShoppingListService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Shopping List')]
final class ShoppingListController extends AbstractController
{
    public function __construct(
        private readonly ShoppingListService $shoppingListService
    ) {
    }

    #[Route('/shopping-list', name: 'api_internal_v1_shopping_list_index', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get shopping list',
        description: 'Returns all shopping list items, ordered by done status then creation date.'
    )]
    #[OA\Response(response: 200, description: 'Shopping list items', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: ShoppingItemResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function index(): JsonResponse
    {
        $items = $this->shoppingListService->getAll();
        $data = array_map(ShoppingItemResponse::fromEntity(...), $items);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/shopping-list', name: 'api_internal_v1_shopping_list_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Add item to shopping list',
        description: 'Adds an item to the shopping list. If product already exists, merges with existing item (max amount, converts to manual).'
    )]
    #[OA\RequestBody(required: true, content: new Model(type: AddShoppingItemRequest::class))]
    #[OA\Response(response: 201, description: 'Item created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ShoppingItemResponse::class))
    ]))]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function create(#[MapRequestPayload] AddShoppingItemRequest $request): JsonResponse
    {
        $item = $this->shoppingListService->addItem($request);

        return $this->json([
            'data' => ShoppingItemResponse::fromEntity($item)
        ], Response::HTTP_CREATED);
    }

    #[Route(
        '/shopping-list/{uuid}',
        name: 'api_internal_v1_shopping_list_show',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['GET']
    )]
    #[OA\Get(summary: 'Get shopping list item', description: 'Returns a single shopping list item.')]
    #[OA\Response(response: 200, description: 'Shopping list item', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ShoppingItemResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Item not found')]
    public function show(Uuid $uuid): JsonResponse
    {
        $item = $this->shoppingListService->getItem($uuid);

        return $this->json([
            'data' => ShoppingItemResponse::fromEntity($item)
        ]);
    }

    #[Route(
        '/shopping-list/{uuid}',
        name: 'api_internal_v1_shopping_list_update',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['PATCH']
    )]
    #[OA\Patch(summary: 'Update shopping list item', description: 'Updates an item amount, note, or done status.')]
    #[OA\RequestBody(required: true, content: new Model(type: UpdateShoppingItemRequest::class))]
    #[OA\Response(response: 200, description: 'Updated item', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ShoppingItemResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Item not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(Uuid $uuid, #[MapRequestPayload] UpdateShoppingItemRequest $request): JsonResponse
    {
        $item = $this->shoppingListService->updateItem($uuid, $request);

        return $this->json([
            'data' => ShoppingItemResponse::fromEntity($item)
        ]);
    }

    #[Route(
        '/shopping-list/{uuid}',
        name: 'api_internal_v1_shopping_list_delete',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['DELETE']
    )]
    #[OA\Delete(summary: 'Delete shopping list item', description: 'Removes an item from the shopping list.')]
    #[OA\Response(response: 204, description: 'Item deleted')]
    #[OA\Response(response: 404, description: 'Item not found')]
    public function delete(Uuid $uuid): JsonResponse
    {
        $this->shoppingListService->deleteItem($uuid);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/shopping-list/clear-completed', name: 'api_internal_v1_shopping_list_clear', methods: ['POST'])]
    #[OA\Post(
        summary: 'Clear completed items',
        description: 'Removes all items marked as done from the shopping list.'
    )]
    #[OA\Response(response: 200, description: 'Items cleared', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            properties: [new OA\Property(property: 'cleared', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function clearCompleted(): JsonResponse
    {
        $count = $this->shoppingListService->clearCompleted();

        return $this->json([
            'data' => ['cleared' => $count]
        ]);
    }
}
