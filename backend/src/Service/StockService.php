<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\Stock;
use App\Entity\StockMovement;
use App\Entity\StockMovementType;
use App\Exception\Product\LocationNotFoundException;
use App\Exception\Product\ProductNotFoundException;
use App\Exception\Stock\InsufficientStockException;
use App\Repository\LocationRepository;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use App\Request\CreateStockMovementRequest;
use App\Response\Stock\StockLocationResponse;
use App\Response\Stock\StockSummaryResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class StockService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockRepository $stockRepository,
        private readonly ProductRepository $productRepository,
        private readonly LocationRepository $locationRepository
    ) {
    }

    public function createMovement(CreateStockMovementRequest $request): StockMovement
    {
        $productId = Uuid::fromString($request->product_id);
        $locationId = Uuid::fromString($request->location_id);

        $product = $this->productRepository->find($productId);
        if ($product === null) {
            throw new ProductNotFoundException($productId);
        }

        $location = $this->locationRepository->find($locationId);
        if ($location === null) {
            throw new LocationNotFoundException($locationId);
        }

        $stock = $this->stockRepository->findByProductAndLocation($productId, $locationId);
        if ($stock === null) {
            $stock = new Stock();
            $stock->setProduct($product);
            $stock->setLocation($location);
            $this->entityManager->persist($stock);
        }

        $movementType = StockMovementType::from($request->type);
        $newQuantity = $this->calculateNewQuantity($stock->getQuantity(), $movementType, $request->quantity);

        if ($newQuantity < 0) {
            throw new InsufficientStockException($request->quantity, $stock->getQuantity());
        }

        $stock->setQuantity($newQuantity);

        $movement = new StockMovement();
        $movement->setStock($stock);
        $movement->setType($movementType);
        $movement->setQuantity($request->quantity);
        $movement->setNotes($request->notes);

        $this->entityManager->persist($movement);
        $this->entityManager->flush();

        return $movement;
    }

    /**
     * @return Stock[]
     */
    // @mago-ignore lint:no-boolean-flag-parameter
    public function listStocks(?string $locationId, bool $lowStockOnly): array
    {
        $locationUuid = $locationId !== null ? Uuid::fromString($locationId) : null;

        return $this->stockRepository->findByFilters($locationUuid, $lowStockOnly);
    }

    public function getStockSummaryForProduct(Uuid $productId): StockSummaryResponse
    {
        $stockData = $this->stockRepository->getStockSummaryForProduct($productId);

        $totalQuantity = 0;
        $locations = [];

        foreach ($stockData as $row) {
            $totalQuantity += $row['quantity'];
            $locations[] = new StockLocationResponse(
                location_id: $row['location_id'],
                location_name: $row['location_name'],
                quantity: $row['quantity']
            );
        }

        return new StockSummaryResponse(total_quantity: $totalQuantity, locations: $locations);
    }

    private function calculateNewQuantity(int $currentQuantity, StockMovementType $type, int $movementQuantity): int
    {
        return match ($type) {
            StockMovementType::ADD => $currentQuantity + $movementQuantity,
            StockMovementType::REMOVE => $currentQuantity - $movementQuantity,
            StockMovementType::ADJUST => $movementQuantity
        };
    }
}
