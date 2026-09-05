<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\StockEntry;
use App\Exception\Product\InvalidLocationReferenceException;
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
use App\Response\Stock\ConsumeResultResponse;
use App\Response\Stock\ExpiringEntryResponse;
use App\Response\Stock\LocationQuantityResponse;
use App\Response\Stock\ProductBriefResponse;
use App\Response\Stock\ProductSummaryResponse;
use App\Response\Stock\StockEntryResponse;
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
            throw new InvalidLocationReferenceException($locationId);
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
            throw new InvalidLocationReferenceException($locationId);
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
     * NOTE: Unlike the other consume/add methods, this method does NOT dispatch a
     * StockChangedMessage itself. The caller is responsible for triggering reconciliation
     * AFTER its transaction commits, because this method is used inside a caller-managed
     * transaction (RecipeService::cook).
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
                throw new InvalidLocationReferenceException($locationId);
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

        // Three queries regardless of product count: the summary rows, the
        // products they name, and one grouped location breakdown for all of them.
        $products = $this->productRepository->findByIdsIndexed(array_map(
            static fn(array $row): Uuid => Uuid::fromString($row['product_id']),
            $summaryData
        ));

        $locationsByProduct = [];
        foreach ($this->stockEntryRepository->getLocationBreakdownForAll() as $loc) {
            $locationsByProduct[$loc['product_id']][] = new LocationQuantityResponse(
                id: Uuid::fromString($loc['location_id']),
                name: $loc['location_name'],
                quantity: (int) $loc['quantity']
            );
        }

        $result = [];
        foreach ($summaryData as $row) {
            $product = $products[$row['product_id']] ?? null;
            if ($product === null) {
                continue;
            }

            $result[] = new ProductSummaryResponse(
                product: ProductBriefResponse::fromEntity($product),
                total_quantity: (int) $row['total_quantity'],
                earliest_expiry: $row['earliest_expiry'] instanceof \DateTimeInterface
                    ? $row['earliest_expiry']->format('Y-m-d')
                    : null,
                locations: $locationsByProduct[$row['product_id']] ?? []
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
            default => $this->stockEntryRepository->findAllWithRelations()
        };

        return array_map(fn(StockEntry $entry): StockEntryResponse => StockEntryResponse::fromEntity(
            $entry,
            $this->daysUntilExpiry($entry)
        ), $entries);
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

        return StockEntryResponse::fromEntity($entry, $this->daysUntilExpiry($entry));
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

                return ExpiringEntryResponse::fromEntity($entry, $this->householdCalendar->daysUntil($bestBefore));
            },
            $entries
        );
    }

    private function daysUntilExpiry(StockEntry $entry): ?int
    {
        $bestBefore = $entry->getBestBefore();

        return $bestBefore !== null ? $this->householdCalendar->daysUntil($bestBefore) : null;
    }
}
