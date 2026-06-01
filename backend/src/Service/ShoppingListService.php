<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\ShoppingListItem;
use App\Enum\ShoppingListSource;
use App\Exception\ShoppingList\ShoppingListItemNotFoundException;
use App\Repository\ProductRepository;
use App\Repository\ShoppingListItemRepository;
use App\Request\AddShoppingItemRequest;
use App\Request\UpdateShoppingItemRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

// @mago-ignore lint:cyclomatic-complexity
readonly class ShoppingListService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ShoppingListItemRepository $shoppingListItemRepository,
        private ProductRepository $productRepository
    ) {
    }

    /**
     * Handle stock change event - auto-add or auto-remove shopping list items.
     *
     * @param Uuid $productId      The product that changed
     * @param int  $_previousQty   Previous total stock quantity (unused; kept for the event contract)
     * @param int  $newQty         New total stock quantity
     */
    public function handleStockChange(Uuid $productId, int $_previousQty, int $newQty): void
    {
        $product = $this->productRepository->find($productId);
        if ($product === null || !$product->isActive()) {
            return;
        }

        $minStock = $product->getMinStock();
        // When minStock=0, deficit will be 0, triggering cleanup of any stale auto items
        // @infection-ignore-all: Equivalent mutant - max(-1, x) behaves same as max(0, x) when next check is `> 0`
        $deficit = max(0, $minStock - $newQty);

        if ($deficit > 0) {
            // Stock is below minimum - add or update auto item
            $this->upsertAutoItem($product, $deficit);

            return;
        }

        // Stock is at or above minimum - remove auto item if exists
        $this->removeAutoItem($product);
    }

    /**
     * Add or update an auto-generated shopping list item.
     * Amount tracks current deficit (updates both up and down).
     */
    private function upsertAutoItem(Product $product, int $deficit): void
    {
        $existing = $this->shoppingListItemRepository->findByProduct($product);

        if ($existing !== null) {
            // If it's a manual item, don't touch it
            if ($existing->getSource() !== ShoppingListSource::AUTO) {
                return;
            }

            // Update amount to current deficit
            if ($deficit !== $existing->getAmount()) {
                $existing->setAmount($deficit);
                $this->entityManager->flush();
            }

            return;
        }

        // Create new auto item
        $item = new ShoppingListItem();
        $item->setProduct($product);
        $item->setAmount($deficit);
        $item->setSource(ShoppingListSource::AUTO);

        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }

    /**
     * Remove an auto-generated item when stock is sufficient.
     * Only removes items with source=auto.
     */
    private function removeAutoItem(Product $product): void
    {
        $item = $this->shoppingListItemRepository->findAutoItemByProduct($product);
        if ($item === null) {
            return;
        }

        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }

    /**
     * Add a manual item to the shopping list.
     * If product already exists, converts to manual and uses max(existing, new) for amount.
     */
    // @mago-ignore lint:halstead
    public function addItem(AddShoppingItemRequest $request): ShoppingListItem
    {
        $product = null;
        if ($request->product_id !== null) {
            $product = $this->productRepository->find(Uuid::fromString($request->product_id));
        }

        // Check for existing item with same product
        if ($product !== null) {
            $existing = $this->shoppingListItemRepository->findByProduct($product);
            if ($existing !== null) {
                // Merge: use max amount, convert to manual
                $existing->setAmount(max($existing->getAmount(), $request->amount));
                $existing->setSource(ShoppingListSource::MANUAL);
                if ($request->note !== null) {
                    $existing->setNote($request->note);
                }

                $this->entityManager->flush();

                return $existing;
            }
        }

        // Create new item
        $item = new ShoppingListItem();
        $item->setProduct($product);
        $item->setCustomName($request->custom_name);
        $item->setAmount($request->amount);
        $item->setNote($request->note);
        // @infection-ignore-all: Equivalent mutant - entity property defaults to MANUAL
        $item->setSource(ShoppingListSource::MANUAL);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }

    /**
     * Update an existing shopping list item.
     * Converting AUTO to MANUAL if amount is changed.
     */
    public function updateItem(Uuid $id, UpdateShoppingItemRequest $request): ShoppingListItem
    {
        $item = $this->shoppingListItemRepository->find($id);
        if ($item === null) {
            throw new ShoppingListItemNotFoundException($id);
        }

        // If user manually changes amount on an AUTO item, convert to MANUAL
        if (
            $request->amount !== null
            && $item->getSource() === ShoppingListSource::AUTO
            && $request->amount !== $item->getAmount()
        ) {
            $item->setSource(ShoppingListSource::MANUAL);
        }

        if ($request->amount !== null) {
            $item->setAmount($request->amount);
        }

        if ($request->note !== null) {
            $item->setNote($request->note);
        }

        if ($request->done !== null) {
            $item->setDone($request->done);
        }

        $this->entityManager->flush();

        return $item;
    }

    /**
     * Delete a shopping list item.
     */
    public function deleteItem(Uuid $id): void
    {
        $item = $this->shoppingListItemRepository->find($id);
        if ($item === null) {
            throw new ShoppingListItemNotFoundException($id);
        }

        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }

    /**
     * Get all shopping list items.
     *
     * @return ShoppingListItem[]
     */
    public function getAll(): array
    {
        return $this->shoppingListItemRepository->findAllOrdered();
    }

    /**
     * Get a single shopping list item.
     */
    public function getItem(Uuid $id): ShoppingListItem
    {
        $item = $this->shoppingListItemRepository->find($id);
        if ($item === null) {
            throw new ShoppingListItemNotFoundException($id);
        }

        return $item;
    }

    /**
     * Clear all completed items.
     *
     * @return int Number of items cleared
     */
    public function clearCompleted(): int
    {
        return $this->shoppingListItemRepository->deleteCompleted();
    }
}
