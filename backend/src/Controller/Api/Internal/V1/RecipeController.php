<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Exception\ApiProblem;
use App\Request\SaveRecipeRequest;
use App\Response\Recipe\RecipeResponse;
use App\Service\RecipeService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Recipes')]
final class RecipeController extends AbstractController
{
    public function __construct(
        private readonly RecipeService $recipeService
    ) {
    }

    #[Route('/recipes', name: 'api_internal_v1_recipes_index', methods: ['GET'])]
    #[OA\Get(description: 'Returns all recipes ordered by name.', summary: 'List recipes')]
    #[OA\Response(response: 200, description: 'List of recipes', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: RecipeResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function index(): JsonResponse
    {
        $items = $this->recipeService->list();

        return $this->json([
            'data' => $items,
            'meta' => ['total' => count($items)]
        ]);
    }

    #[Route(
        '/recipes/{uuid}',
        name: 'api_internal_v1_recipes_show',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['GET']
    )]
    #[OA\Get(description: 'Returns a single recipe by its UUID.', summary: 'Get recipe')]
    #[OA\Response(response: 200, description: 'Recipe details', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: RecipeResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Recipe not found', content: new Model(type: ApiProblem::class))]
    public function show(Uuid $uuid): JsonResponse
    {
        return $this->json([
            'data' => $this->recipeService->getResponse($uuid)
        ]);
    }

    #[Route('/recipes', name: 'api_internal_v1_recipes_create', methods: ['POST'])]
    #[OA\Post(description: 'Creates a new recipe.', summary: 'Create recipe')]
    #[OA\RequestBody(required: true, content: new Model(type: SaveRecipeRequest::class))]
    #[OA\Response(response: 201, description: 'Recipe created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: RecipeResponse::class))
    ]))]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function create(#[MapRequestPayload] SaveRecipeRequest $request): JsonResponse
    {
        return $this->json([
            'data' => $this->recipeService->create($request)
        ], Response::HTTP_CREATED);
    }

    #[Route(
        '/recipes/{uuid}',
        name: 'api_internal_v1_recipes_update',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['PUT']
    )]
    #[OA\Put(description: 'Updates an existing recipe.', summary: 'Update recipe')]
    #[OA\RequestBody(required: true, content: new Model(type: SaveRecipeRequest::class))]
    #[OA\Response(response: 200, description: 'Recipe updated', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: RecipeResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Recipe not found', content: new Model(type: ApiProblem::class))]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(Uuid $uuid, #[MapRequestPayload] SaveRecipeRequest $request): JsonResponse
    {
        return $this->json([
            'data' => $this->recipeService->update($uuid, $request)
        ]);
    }

    #[Route(
        '/recipes/{uuid}',
        name: 'api_internal_v1_recipes_delete',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['DELETE']
    )]
    #[OA\Delete(description: 'Deletes a recipe.', summary: 'Delete recipe')]
    #[OA\Response(response: 204, description: 'Recipe deleted')]
    #[OA\Response(response: 404, description: 'Recipe not found', content: new Model(type: ApiProblem::class))]
    public function delete(Uuid $uuid): JsonResponse
    {
        $this->recipeService->delete($uuid);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/recipes/{uuid}/cook',
        name: 'api_internal_v1_recipes_cook',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['POST']
    )]
    #[OA\Post(description: 'Cooks the recipe, consuming ingredients marked consume_on_cook.', summary: 'Cook recipe')]
    #[OA\Response(response: 200, description: 'Recipe cooked', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: RecipeResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Recipe not found', content: new Model(type: ApiProblem::class))]
    #[OA\Response(
        response: 409,
        description: 'Recipe not cookable (missing ingredients)',
        content: new Model(type: ApiProblem::class)
    )]
    public function cook(Uuid $uuid): JsonResponse
    {
        return $this->json([
            'data' => $this->recipeService->cook($uuid)
        ]);
    }

    #[Route(
        '/recipes/{uuid}/add-missing-to-shopping-list',
        name: 'api_internal_v1_recipes_add_missing',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['POST']
    )]
    #[OA\Post(
        description: 'Adds missing ingredients (shortfall) to the shopping list with RECIPE source.',
        summary: 'Add missing ingredients to shopping list'
    )]
    #[OA\Response(response: 200, description: 'Missing ingredients added', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            properties: [new OA\Property(property: 'added', type: 'integer')],
            type: 'object'
        )
    ]))]
    #[OA\Response(response: 404, description: 'Recipe not found', content: new Model(type: ApiProblem::class))]
    public function addMissing(Uuid $uuid): JsonResponse
    {
        return $this->json([
            'data' => $this->recipeService->addMissingToShoppingList($uuid)
        ]);
    }
}
