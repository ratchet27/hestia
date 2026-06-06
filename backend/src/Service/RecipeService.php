<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Entity\ShoppingListItem;
use App\Enum\ShoppingListSource;
use App\Exception\Product\ProductNotFoundException;
use App\Exception\Recipe\RecipeNotCookableException;
use App\Exception\Recipe\RecipeNotFoundException;
use App\Message\StockChangedMessage;
use App\Repository\ProductRepository;
use App\Repository\RecipeRepository;
use App\Repository\ShoppingListItemRepository;
use App\Repository\StockEntryRepository;
use App\Request\SaveRecipeRequest;
use App\Response\Recipe\RecipeIngredientResponse;
use App\Response\Recipe\RecipeResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

// @mago-ignore lint:kan-defect
readonly class RecipeService
{
    // Reads stock counts via StockEntryRepository (fulfillment) and consumes via StockEntryService (cook);
    // both are genuinely needed. MessageBusInterface dispatches reconciliation post-commit in cook.
    // @mago-ignore lint:excessive-parameter-list
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RecipeRepository $recipeRepository,
        private ProductRepository $productRepository,
        private StockEntryRepository $stockEntryRepository,
        private ShoppingListItemRepository $shoppingListItemRepository,
        private StockEntryService $stockEntryService,
        private MessageBusInterface $messageBus
    ) {
    }

    /** @return RecipeResponse[] */
    public function list(): array
    {
        return array_map($this->toResponse(...), $this->recipeRepository->findAllOrdered());
    }

    public function getResponse(Uuid $id): RecipeResponse
    {
        return $this->toResponse($this->getRecipe($id));
    }

    public function create(SaveRecipeRequest $request): RecipeResponse
    {
        $recipe = new Recipe();
        $this->applyRequest($recipe, $request);
        $this->entityManager->persist($recipe);
        $this->entityManager->flush();

        return $this->toResponse($recipe);
    }

    public function update(Uuid $id, SaveRecipeRequest $request): RecipeResponse
    {
        $recipe = $this->getRecipe($id);

        $this->entityManager->wrapInTransaction(function () use ($recipe, $request): void {
            $recipe->clearIngredients();
            $this->entityManager->flush(); // process orphan removals before re-inserting
            $this->applyRequest($recipe, $request);
            $this->entityManager->flush();
        });

        return $this->toResponse($recipe);
    }

    public function delete(Uuid $id): void
    {
        $recipe = $this->getRecipe($id);
        $this->entityManager->remove($recipe);
        $this->entityManager->flush();
    }

    public function cook(Uuid $id): RecipeResponse
    {
        $recipe = $this->getRecipe($id);

        $missing = [];
        foreach ($recipe->getIngredients() as $ingredient) {
            $inStock = $this->stockEntryRepository->countByProduct($ingredient->getProduct()->getId());
            if ($inStock < $ingredient->getRequiredCount()) {
                $missing[] = $ingredient->getProduct()->getName();
            }
        }

        if ($missing !== []) {
            throw new RecipeNotCookableException($id, $missing);
        }

        /** @var array<string, Uuid> $consumedProductIds */
        $consumedProductIds = [];
        $this->entityManager->wrapInTransaction(function () use ($recipe, &$consumedProductIds): void {
            foreach ($recipe->getIngredients() as $ingredient) {
                if (!$ingredient->isConsumeOnCook()) {
                    continue;
                }

                $productId = $ingredient->getProduct()->getId();
                $this->stockEntryService->consumeAcrossLocations($productId, $ingredient->getRequiredCount());
                $consumedProductIds[(string) $productId] = $productId; // dedupe by string key
            }
        });

        // Reconcile shopping list AFTER the cook transaction has committed,
        // so the handler sees durable stock counts (not an in-flight transaction).
        foreach ($consumedProductIds as $productId) {
            $this->messageBus->dispatch(new StockChangedMessage($productId));
        }

        return $this->toResponse($recipe);
    }

    /** @return array{added: int} */
    public function addMissingToShoppingList(Uuid $id): array
    {
        $recipe = $this->getRecipe($id);
        $added = 0;

        foreach ($recipe->getIngredients() as $ingredient) {
            $product = $ingredient->getProduct();
            $inStock = $this->stockEntryRepository->countByProduct($product->getId());
            $shortfall = max(0, $ingredient->getRequiredCount() - $inStock);
            if ($shortfall === 0) {
                continue;
            }

            if ($this->shoppingListItemRepository->findByProduct($product) !== null) {
                continue;
            }

            $item = new ShoppingListItem();
            $item->setProduct($product);
            $item->setAmount($shortfall);
            $item->setSource(ShoppingListSource::RECIPE);
            $this->entityManager->persist($item);
            $added++;
        }

        $this->entityManager->flush();

        return ['added' => $added];
    }

    private function getRecipe(Uuid $id): Recipe
    {
        $recipe = $this->recipeRepository->find($id);
        if ($recipe === null) {
            throw new RecipeNotFoundException($id);
        }

        return $recipe;
    }

    private function applyRequest(Recipe $recipe, SaveRecipeRequest $request): void
    {
        $recipe->setName($request->name);
        $recipe->setInstructions($request->instructions);
        $recipe->setSourceUrl($request->source_url);

        foreach ($request->ingredients as $payload) {
            $productId = Uuid::fromString($payload->product_id);
            $product = $this->productRepository->find($productId);
            if ($product === null) {
                throw new ProductNotFoundException($productId);
            }

            $ingredient = new RecipeIngredient();
            $ingredient->setProduct($product);
            $ingredient->setRequiredCount($payload->required_count);
            $ingredient->setConsumeOnCook($payload->consume_on_cook);
            $recipe->addIngredient($ingredient);
        }
    }

    private function toResponse(Recipe $recipe): RecipeResponse
    {
        $ingredients = [];
        $cookable = true;

        foreach ($recipe->getIngredients() as $ingredient) {
            $product = $ingredient->getProduct();
            // TODO: if recipe lists grow, replace per-ingredient COUNT with a single bulk stock-count query.
            $inStock = $this->stockEntryRepository->countByProduct($product->getId());
            $hasEnough = $inStock >= $ingredient->getRequiredCount();
            if (!$hasEnough) {
                $cookable = false;
            }

            $ingredients[] = new RecipeIngredientResponse(
                id: $ingredient->getId(),
                product_id: $product->getId(),
                product_name: $product->getName(),
                required_count: $ingredient->getRequiredCount(),
                consume_on_cook: $ingredient->isConsumeOnCook(),
                in_stock: $inStock,
                has_enough: $hasEnough,
                shortfall: max(0, $ingredient->getRequiredCount() - $inStock),
                product_inactive: !$product->isActive()
            );
        }

        return new RecipeResponse(
            id: $recipe->getId(),
            name: $recipe->getName(),
            instructions: $recipe->getInstructions(),
            source_url: $recipe->getSourceUrl(),
            cookable: $ingredients === [] ? false : $cookable,
            ingredients: $ingredients,
            created_at: $recipe->getCreatedAt()
        );
    }
}
