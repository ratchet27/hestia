<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\StockEntry;
use App\Exception\Product\LocationNotFoundException;
use App\Exception\Product\ProductNotActiveException;
use App\Exception\Product\ProductNotFoundException;
use App\Exception\Stock\InsufficientStockException;
use App\Exception\Stock\StockEntryNotFoundException;
use App\Repository\LocationRepository;
use App\Repository\ProductRepository;
use App\Repository\StockEntryRepository;
use App\Request\AddStockRequest;
use App\Request\ConsumeStockRequest;
use App\Request\UpdateStockEntryRequest;
use App\Response\Location\LocationResponse;
use App\Response\Stock\ConsumeResultResponse;
use App\Response\Stock\ExpiringEntryResponse;
use App\Response\Stock\LocationQuantityResponse;
use App\Response\Stock\ProductBriefResponse;
use App\Response\Stock\ProductSummaryResponse;
use App\Response\Stock\StockEntryResponse;
use App\Response\Stock\StockSummaryResponse;
use App\Message\StockChangedMessage;
use App\Service\Time\HouseholdCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

// @mago-ignore lint:cyclomatic-complexity
// @mago-ignore lint:kan-defect
class StockEntryService
{
    // @mago-ignore lint:excessive-parameter-list
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockEntryRepository $stockEntryRepository,
        private readonly ProductRepository $productRepository,
        private readonly LocationRepository $locationRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly HouseholdCalendar $householdCalendar
    ) {
    }

    /**
     * Add stock entries (creates N entries for quantity N).
     *
     * @return StockEntry[]
     */
    public function addStock(AddStockRequest $request): array
    {
        $productId = Uuid::fromString($request->product_id);
        $locationId = Uuid::fromString($request->location_id);

        $product = $this->productRepository->find($productId);
        if ($product === null) {
            throw new ProductNotFoundException($productId);
        }

        if (!$product->isActive()) {
            throw new ProductNotActiveException($productId);
        }

        $location = $this->locationRepository->find($locationId);
        if ($location === null) {
            throw new LocationNotFoundException($locationId);
        }

        // Calculate best_before
        $bestBefore = match (true) {
            $request->best_before !== null => new \DateTimeImmutable($request->best_before),
            $product->getDefaultExpiryDays() !== null => $this->householdCalendar
                ->today()
                ->modify(sprintf('+%d days', (int) $product->getDefaultExpiryDays())),
            default => null
        };

        $entries = [];
        for ($i = 0; $i < $request->quantity; $i++) {
            $entry = new StockEntry();
            $entry->setProduct($product);
            $entry->setLocation($location);
            $entry->setBestBefore($bestBefore);
            $this->entityManager->persist($entry);
            $entries[] = $entry;
        }

        $this->entityManager->flush();

        // Dispatch stock change event
        $this->messageBus->dispatch(new StockChangedMessage($productId));

        return $entries;
    }

    /**
     * Consume stock entries (deletes N entries in FIFO order).
     */
    public function consumeStock(ConsumeStockRequest $request): ConsumeResultResponse
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

        $available = $this->stockEntryRepository->countByProductAndLocation($productId, $locationId);
        if ($available < $request->quantity) {
            throw new InsufficientStockException($request->quantity, $available);
        }

        $entries = $this->stockEntryRepository->findForFifoConsumption($productId, $locationId, $request->quantity);
        $deletedIds = [];

        foreach ($entries as $entry) {
            $deletedIds[] = $entry->getId();
            $this->entityManager->remove($entry);
        }

        $this->entityManager->flush();

        $remaining = $this->stockEntryRepository->countByProductAndLocation($productId, $locationId);

        // Dispatch stock change event
        $this->messageBus->dispatch(new StockChangedMessage($productId));

        return new ConsumeResultResponse(
            consumed: count($deletedIds),
            deleted_entries: $deletedIds,
            remaining_at_location: $remaining
        );
    }

    /**
     * Consume N entries of a product across all locations (FIFO, earliest expiry first).
     *
     * @return int Number of entries consumed
     */
    public function consumeAcrossLocations(Uuid $productId, int $quantity): int
    {
        $previousQty = $this->stockEntryRepository->countByProduct($productId);
        if ($previousQty < $quantity) {
            throw new InsufficientStockException($quantity, $previousQty);
        }

        $entries = $this->stockEntryRepository->findForFifoConsumptionAcrossLocations($productId, $quantity);
        foreach ($entries as $entry) {
            $this->entityManager->remove($entry);
        }

        $this->entityManager->flush();

        $this->messageBus->dispatch(new StockChangedMessage($productId));

        return count($entries);
    }

    /**
     * Update a stock entry (location and/or best_before).
     */
    public function updateEntry(Uuid $entryId, UpdateStockEntryRequest $request): StockEntry
    {
        $entry = $this->stockEntryRepository->find($entryId);
        if ($entry === null) {
            throw new StockEntryNotFoundException($entryId);
        }

        if ($request->location_id !== null) {
            $locationId = Uuid::fromString($request->location_id);
            $location = $this->locationRepository->find($locationId);
            if ($location === null) {
                throw new LocationNotFoundException($locationId);
            }

            $entry->setLocation($location);
        }

        if ($request->best_before !== null) {
            $entry->setBestBefore(new \DateTimeImmutable($request->best_before));
        }

        $this->entityManager->flush();

        return $entry;
    }

    /**
     * Delete a stock entry.
     */
    public function deleteEntry(Uuid $entryId): void
    {
        $entry = $this->stockEntryRepository->find($entryId);
        if ($entry === null) {
            throw new StockEntryNotFoundException($entryId);
        }

        $productId = $entry->getProduct()->getId();
        $this->entityManager->remove($entry);
        $this->entityManager->flush();

        // Dispatch stock change event
        $this->messageBus->dispatch(new StockChangedMessage($productId));
    }

    /**
     * Get stock summary (aggregated by product).
     *
     * @return ProductSummaryResponse[]
     */
    // @mago-ignore lint:no-boolean-flag-parameter
    // @infection-ignore-all: Equivalent mutant - controller always passes explicit value from query param
    public function getStockSummary(bool $lowStockOnly = false): array
    {
        $summaryData = $lowStockOnly
            ? $this->stockEntryRepository->getStockSummaryLowStock()
            : $this->stockEntryRepository->getStockSummary();

        $result = [];
        foreach ($summaryData as $row) {
            $productId = Uuid::fromString($row['product_id']);
            $product = $this->productRepository->find($productId);
            if ($product === null) {
                continue;
            }

            $locationBreakdown = $this->stockEntryRepository->getLocationBreakdown($productId);
            $locations = array_map(
                static fn(array $loc) => new LocationQuantityResponse(
                    id: Uuid::fromString($loc['location_id']),
                    name: $loc['location_name'],
                    quantity: (int) $loc['quantity']
                ),
                $locationBreakdown
            );

            $result[] = new ProductSummaryResponse(
                product: new ProductBriefResponse(
                    id: $product->getId(),
                    name: $product->getName(),
                    unit: $product->getUnit()
                ),
                total_quantity: (int) $row['total_quantity'],
                earliest_expiry: $row['earliest_expiry'] instanceof \DateTimeInterface
                    ? $row['earliest_expiry']->format('Y-m-d')
                    : null,
                locations: $locations
            );
        }

        return $result;
    }

    /**
     * Get all entries, optionally filtered.
     *
     * @return StockEntryResponse[]
     */
    public function getEntries(?Uuid $locationId = null, ?Uuid $productId = null): array
    {
        $entries = match (true) {
            $locationId !== null => $this->stockEntryRepository->findByLocation($locationId),
            $productId !== null => $this->stockEntryRepository->findByProduct($productId),
            default => $this->stockEntryRepository->findAll()
        };

        return array_map($this->mapEntryToResponse(...), $entries);
    }

    /**
     * Get a single entry.
     */
    public function getEntry(Uuid $entryId): StockEntryResponse
    {
        $entry = $this->stockEntryRepository->find($entryId);
        if ($entry === null) {
            throw new StockEntryNotFoundException($entryId);
        }

        return $this->mapEntryToResponse($entry);
    }

    /**
     * Get expiring entries (includes already expired, ordered by urgency).
     *
     * @return ExpiringEntryResponse[]
     */
    public function getExpiringEntries(int $days): array
    {
        $entries = $this->stockEntryRepository->findExpiring($this->householdCalendar->expiryCutoff($days));

        return array_map(
            function (StockEntry $entry): ExpiringEntryResponse {
                /** @var \DateTimeImmutable $bestBefore - guaranteed non-null by findExpiring query */
                $bestBefore = $entry->getBestBefore();

                return new ExpiringEntryResponse(
                    id: $entry->getId(),
                    product: new ProductBriefResponse(
                        id: $entry->getProduct()->getId(),
                        name: $entry->getProduct()->getName(),
                        unit: $entry->getProduct()->getUnit()
                    ),
                    location: new LocationResponse(
                        id: $entry->getLocation()->getId(),
                        name: $entry->getLocation()->getName()
                    ),
                    best_before: $bestBefore->format('Y-m-d'),
                    days_until_expiry: $this->householdCalendar->daysUntil($bestBefore)
                );
            },
            $entries
        );
    }

    /**
     * Get stock summary for a single product (used by ProductController).
     */
    public function getStockSummaryForProduct(Uuid $productId): ?StockSummaryResponse
    {
        $totalQuantity = $this->stockEntryRepository->countByProduct($productId);
        if ($totalQuantity === 0) {
            return null;
        }

        $locationBreakdown = $this->stockEntryRepository->getLocationBreakdown($productId);
        $locations = array_map(
            static fn(array $loc) => new LocationQuantityResponse(
                id: Uuid::fromString($loc['location_id']),
                name: $loc['location_name'],
                quantity: (int) $loc['quantity']
            ),
            $locationBreakdown
        );

        // Get earliest expiry
        $entries = $this->stockEntryRepository->findByProduct($productId);
        $earliestExpiry = null;
        foreach ($entries as $entry) {
            $bestBefore = $entry->getBestBefore();
            if ($bestBefore !== null && ( $earliestExpiry === null || $bestBefore < $earliestExpiry )) {
                $earliestExpiry = $bestBefore;
            }
        }

        return new StockSummaryResponse(
            total_quantity: $totalQuantity,
            earliest_expiry: $earliestExpiry?->format('Y-m-d'),
            locations: $locations
        );
    }

    private function mapEntryToResponse(StockEntry $entry): StockEntryResponse
    {
        $bestBefore = $entry->getBestBefore();

        return new StockEntryResponse(
            id: $entry->getId(),
            product: new ProductBriefResponse(
                id: $entry->getProduct()->getId(),
                name: $entry->getProduct()->getName(),
                unit: $entry->getProduct()->getUnit()
            ),
            location: new LocationResponse(id: $entry->getLocation()->getId(), name: $entry->getLocation()->getName()),
            best_before: $bestBefore?->format('Y-m-d'),
            created_at: $entry->getCreatedAt(),
            days_until_expiry: $bestBefore !== null ? $this->householdCalendar->daysUntil($bestBefore) : null
        );
    }
}
