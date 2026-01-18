# Stock Entry Refactoring Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace aggregate-based stock tracking with entry-per-item model where each unit is one row and consume = delete.

**Architecture:** StockEntry entity (one row per unit), StockEntryRepository (FIFO queries), StockEntryService (add/consume/update logic), rewritten StockController. No quantity field - count rows instead.

**Tech Stack:** Symfony 8.0, Doctrine ORM, PostgreSQL, PHPUnit 12, Zenstruck Foundry

**Reference:** Design document at `docs/plans/2026-01-19-stock-entries-design.md`

---

## Task 1: Add `unit` field to Product entity

**Files:**
- Modify: `backend/src/Entity/Product.php`
- Modify: `backend/src/Request/CreateProductRequest.php`
- Modify: `backend/src/Request/UpdateProductRequest.php`
- Modify: `backend/src/Response/Product/ProductResponse.php`

**Step 1: Add unit field to Product entity**

In `backend/src/Entity/Product.php`, add after line 47 (`private int $minStock = 0;`):

```php
#[ORM\Column(length: 50, options: ['default' => 'piece'])]
#[Assert\Length(max: 50)]
private string $unit = 'piece';
```

Add getter/setter after `setMinStock()`:

```php
public function getUnit(): string
{
    return $this->unit;
}

public function setUnit(string $unit): static
{
    $this->unit = $unit;

    return $this;
}
```

**Step 2: Add unit to CreateProductRequest**

In `backend/src/Request/CreateProductRequest.php`, add parameter:

```php
#[Assert\Length(max: 50)]
public string $unit = 'piece'
```

**Step 3: Add unit to UpdateProductRequest**

In `backend/src/Request/UpdateProductRequest.php`, add parameter:

```php
#[Assert\Length(max: 50)]
public ?string $unit = null
```

**Step 4: Add unit to ProductResponse**

In `backend/src/Response/Product/ProductResponse.php`, add parameter after `name`:

```php
public string $unit,
```

**Step 5: Run code quality checks**

```bash
docker compose exec php vendor/bin/rector
mago format && mago lint && mago analyze
docker compose exec php vendor/bin/phpstan analyse
```

**Step 6: Commit**

```bash
git add backend/src/Entity/Product.php backend/src/Request/CreateProductRequest.php backend/src/Request/UpdateProductRequest.php backend/src/Response/Product/ProductResponse.php
git commit -s -m "feat(product): add unit field for display label"
```

---

## Task 2: Create StockEntry entity

**Files:**
- Create: `backend/src/Entity/StockEntry.php`
- Create: `backend/src/Repository/StockEntryRepository.php`

**Step 1: Create StockEntry entity**

Create `backend/src/Entity/StockEntry.php`:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StockEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: StockEntryRepository::class)]
#[ORM\Table(name: 'stock_entries')]
#[ORM\Index(name: 'stock_entry_product_idx', columns: ['product_id'])]
#[ORM\Index(name: 'stock_entry_location_idx', columns: ['location_id'])]
#[ORM\Index(name: 'stock_entry_fifo_idx', columns: ['product_id', 'location_id', 'best_before', 'created_at'])]
class StockEntry
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Location $location;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $bestBefore = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function setLocation(Location $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getBestBefore(): ?\DateTimeImmutable
    {
        return $this->bestBefore;
    }

    public function setBestBefore(?\DateTimeImmutable $bestBefore): static
    {
        $this->bestBefore = $bestBefore;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
```

**Step 2: Create StockEntryRepository**

Create `backend/src/Repository/StockEntryRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<StockEntry>
 */
class StockEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockEntry::class);
    }

    /**
     * Find entries for FIFO consumption (earliest best_before first, NULL last, then created_at).
     *
     * @return StockEntry[]
     */
    public function findForFifoConsumption(Uuid $productId, Uuid $locationId, int $limit): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.product = :productId')
            ->andWhere('e.location = :locationId')
            ->setParameter('productId', $productId)
            ->setParameter('locationId', $locationId)
            ->orderBy('CASE WHEN e.bestBefore IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('e.bestBefore', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count entries for a product at a location.
     */
    public function countByProductAndLocation(Uuid $productId, Uuid $locationId): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.product = :productId')
            ->andWhere('e.location = :locationId')
            ->setParameter('productId', $productId)
            ->setParameter('locationId', $locationId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count total entries for a product across all locations.
     */
    public function countByProduct(Uuid $productId): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.product = :productId')
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find entries by location.
     *
     * @return StockEntry[]
     */
    public function findByLocation(Uuid $locationId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.location = :locationId')
            ->setParameter('locationId', $locationId)
            ->orderBy('e.bestBefore', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find entries by product.
     *
     * @return StockEntry[]
     */
    public function findByProduct(Uuid $productId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.product = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('e.bestBefore', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find entries expiring within N days (includes already expired, ordered by urgency).
     *
     * @return StockEntry[]
     */
    public function findExpiring(int $days): array
    {
        $cutoffDate = (new \DateTimeImmutable())->modify("+{$days} days");

        return $this->createQueryBuilder('e')
            ->where('e.bestBefore IS NOT NULL')
            ->andWhere('e.bestBefore <= :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('e.bestBefore', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get stock summary grouped by product.
     *
     * @return array<array{product_id: string, total_quantity: int, earliest_expiry: ?string}>
     */
    public function getStockSummary(): array
    {
        return $this->createQueryBuilder('e')
            ->select(
                'IDENTITY(e.product) as product_id',
                'COUNT(e.id) as total_quantity',
                'MIN(e.bestBefore) as earliest_expiry'
            )
            ->groupBy('e.product')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get stock summary for products with low stock.
     *
     * @return array<array{product_id: string, total_quantity: int, earliest_expiry: ?string}>
     */
    public function getStockSummaryLowStock(): array
    {
        return $this->createQueryBuilder('e')
            ->select(
                'IDENTITY(e.product) as product_id',
                'COUNT(e.id) as total_quantity',
                'MIN(e.bestBefore) as earliest_expiry',
                'p.minStock as min_stock'
            )
            ->join('e.product', 'p')
            ->groupBy('e.product, p.minStock')
            ->having('COUNT(e.id) < p.minStock')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get location breakdown for a product.
     *
     * @return array<array{location_id: string, location_name: string, quantity: int}>
     */
    public function getLocationBreakdown(Uuid $productId): array
    {
        return $this->createQueryBuilder('e')
            ->select(
                'IDENTITY(e.location) as location_id',
                'l.name as location_name',
                'COUNT(e.id) as quantity'
            )
            ->join('e.location', 'l')
            ->where('e.product = :productId')
            ->setParameter('productId', $productId)
            ->groupBy('e.location, l.name')
            ->getQuery()
            ->getResult();
    }
}
```

**Step 3: Run code quality checks**

```bash
docker compose exec php vendor/bin/rector
mago format && mago lint && mago analyze
docker compose exec php vendor/bin/phpstan analyse
```

**Step 4: Commit**

```bash
git add backend/src/Entity/StockEntry.php backend/src/Repository/StockEntryRepository.php
git commit -s -m "feat(stock): add StockEntry entity and repository"
```

---

## Task 3: Create database migration

**Files:**
- Create: `backend/migrations/Version20260119XXXXXX.php` (auto-generated)

**Step 1: Generate migration**

```bash
docker compose exec php bin/console doctrine:migrations:diff
```

**Step 2: Review the generated migration**

Verify it contains:
- `CREATE TABLE stock_entries` with correct columns
- `ALTER TABLE products ADD unit`
- Proper indexes

**Step 3: Run migration**

```bash
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

**Step 4: Validate schema**

```bash
docker compose exec php bin/console doctrine:schema:validate
```

Expected: "OK - The mapping files are correct" and "OK - The database schema is in sync"

**Step 5: Commit**

```bash
git add backend/migrations/
git commit -s -m "feat(stock): add migration for stock_entries table and product.unit"
```

---

## Task 4: Create Request DTOs

**Files:**
- Create: `backend/src/Request/AddStockRequest.php`
- Create: `backend/src/Request/ConsumeStockRequest.php`
- Create: `backend/src/Request/UpdateStockEntryRequest.php`

**Step 1: Create AddStockRequest**

Create `backend/src/Request/AddStockRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddStockRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $product_id,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $location_id,

        #[Assert\Positive]
        public int $quantity = 1,

        #[Assert\Date]
        public ?string $best_before = null
    ) {
    }
}
```

**Step 2: Create ConsumeStockRequest**

Create `backend/src/Request/ConsumeStockRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConsumeStockRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $product_id,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $location_id,

        #[Assert\Positive]
        public int $quantity = 1
    ) {
    }
}
```

**Step 3: Create UpdateStockEntryRequest**

Create `backend/src/Request/UpdateStockEntryRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateStockEntryRequest
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $location_id = null,

        #[Assert\Date]
        public ?string $best_before = null
    ) {
    }
}
```

**Step 4: Run code quality checks**

```bash
docker compose exec php vendor/bin/rector
mago format && mago lint && mago analyze
docker compose exec php vendor/bin/phpstan analyse
```

**Step 5: Commit**

```bash
git add backend/src/Request/AddStockRequest.php backend/src/Request/ConsumeStockRequest.php backend/src/Request/UpdateStockEntryRequest.php
git commit -s -m "feat(stock): add request DTOs for stock entry operations"
```

---

## Task 5: Create Response DTOs

**Files:**
- Create: `backend/src/Response/Stock/StockEntryResponse.php`
- Create: `backend/src/Response/Stock/ProductSummaryResponse.php`
- Create: `backend/src/Response/Stock/ConsumeResultResponse.php`
- Create: `backend/src/Response/Stock/ExpiringEntryResponse.php`
- Modify: `backend/src/Response/Stock/StockSummaryResponse.php`

**Step 1: Create StockEntryResponse**

Create `backend/src/Response/Stock/StockEntryResponse.php`:

```php
<?php

declare(strict_types=1);

namespace App\Response\Stock;

use App\Response\Location\LocationResponse;
use Symfony\Component\Uid\Uuid;

final readonly class StockEntryResponse
{
    public function __construct(
        public Uuid $id,
        public ProductBriefResponse $product,
        public LocationResponse $location,
        public ?string $best_before,
        public \DateTimeImmutable $created_at
    ) {
    }
}
```

**Step 2: Create ProductBriefResponse (for embedding in stock responses)**

Create `backend/src/Response/Stock/ProductBriefResponse.php`:

```php
<?php

declare(strict_types=1);

namespace App\Response\Stock;

use Symfony\Component\Uid\Uuid;

final readonly class ProductBriefResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public string $unit
    ) {
    }
}
```

**Step 3: Create ProductSummaryResponse (for GET /stocks)**

Create `backend/src/Response/Stock/ProductSummaryResponse.php`:

```php
<?php

declare(strict_types=1);

namespace App\Response\Stock;

final readonly class ProductSummaryResponse
{
    /**
     * @param LocationQuantityResponse[] $locations
     */
    public function __construct(
        public ProductBriefResponse $product,
        public int $total_quantity,
        public ?string $earliest_expiry,
        public array $locations
    ) {
    }
}
```

**Step 4: Create LocationQuantityResponse**

Create `backend/src/Response/Stock/LocationQuantityResponse.php`:

```php
<?php

declare(strict_types=1);

namespace App\Response\Stock;

use Symfony\Component\Uid\Uuid;

final readonly class LocationQuantityResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public int $quantity
    ) {
    }
}
```

**Step 5: Create ConsumeResultResponse**

Create `backend/src/Response/Stock/ConsumeResultResponse.php`:

```php
<?php

declare(strict_types=1);

namespace App\Response\Stock;

use Symfony\Component\Uid\Uuid;

final readonly class ConsumeResultResponse
{
    /**
     * @param Uuid[] $deleted_entries
     */
    public function __construct(
        public int $consumed,
        public array $deleted_entries,
        public int $remaining_at_location
    ) {
    }
}
```

**Step 6: Create ExpiringEntryResponse**

Create `backend/src/Response/Stock/ExpiringEntryResponse.php`:

```php
<?php

declare(strict_types=1);

namespace App\Response\Stock;

use App\Response\Location\LocationResponse;
use Symfony\Component\Uid\Uuid;

final readonly class ExpiringEntryResponse
{
    public function __construct(
        public Uuid $id,
        public ProductBriefResponse $product,
        public LocationResponse $location,
        public string $best_before,
        public int $days_until_expiry
    ) {
    }
}
```

**Step 7: Run code quality checks**

```bash
docker compose exec php vendor/bin/rector
mago format && mago lint && mago analyze
docker compose exec php vendor/bin/phpstan analyse
```

**Step 8: Commit**

```bash
git add backend/src/Response/Stock/
git commit -s -m "feat(stock): add response DTOs for stock entry API"
```

---

## Task 6: Create StockEntryService

**Files:**
- Create: `backend/src/Service/StockEntryService.php`
- Modify: `backend/src/Exception/Stock/InsufficientStockException.php`

**Step 1: Update InsufficientStockException**

Modify `backend/src/Exception/Stock/InsufficientStockException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exception\Stock;

use App\Exception\ApiException;
use App\Exception\ApiProblem;

class InsufficientStockException extends ApiException
{
    public function __construct(int $requested, int $available)
    {
        parent::__construct(new ApiProblem(
            title: 'Insufficient stock',
            type: 'INSUFFICIENT_STOCK',
            code: 400,
            extraData: [
                'requested' => $requested,
                'available' => $available,
                'message' => "Cannot consume {$requested} units, only {$available} available at this location"
            ]
        ));
    }
}
```

**Step 2: Create StockEntryService**

Create `backend/src/Service/StockEntryService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\StockEntry;
use App\Exception\Product\LocationNotFoundException;
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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class StockEntryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockEntryRepository $stockEntryRepository,
        private readonly ProductRepository $productRepository,
        private readonly LocationRepository $locationRepository
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

        $location = $this->locationRepository->find($locationId);
        if ($location === null) {
            throw new LocationNotFoundException($locationId);
        }

        // Calculate best_before
        $bestBefore = null;
        if ($request->best_before !== null) {
            $bestBefore = new \DateTimeImmutable($request->best_before);
        } elseif ($product->getDefaultExpiryDays() !== null) {
            $bestBefore = (new \DateTimeImmutable())->modify("+{$product->getDefaultExpiryDays()} days");
        }

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

        return new ConsumeResultResponse(
            consumed: count($deletedIds),
            deleted_entries: $deletedIds,
            remaining_at_location: $remaining
        );
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

        $this->entityManager->remove($entry);
        $this->entityManager->flush();
    }

    /**
     * Get stock summary (aggregated by product).
     *
     * @return ProductSummaryResponse[]
     */
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
                fn(array $loc) => new LocationQuantityResponse(
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
                earliest_expiry: $row['earliest_expiry']?->format('Y-m-d'),
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
        if ($locationId !== null) {
            $entries = $this->stockEntryRepository->findByLocation($locationId);
        } elseif ($productId !== null) {
            $entries = $this->stockEntryRepository->findByProduct($productId);
        } else {
            $entries = $this->stockEntryRepository->findAll();
        }

        return array_map(fn(StockEntry $e) => $this->mapEntryToResponse($e), $entries);
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
        $entries = $this->stockEntryRepository->findExpiring($days);
        $today = new \DateTimeImmutable('today');

        return array_map(function (StockEntry $entry) use ($today): ExpiringEntryResponse {
            $bestBefore = $entry->getBestBefore();
            $daysUntilExpiry = (int) $today->diff($bestBefore)->format('%r%a');

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
                days_until_expiry: $daysUntilExpiry
            );
        }, $entries);
    }

    private function mapEntryToResponse(StockEntry $entry): StockEntryResponse
    {
        return new StockEntryResponse(
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
            best_before: $entry->getBestBefore()?->format('Y-m-d'),
            created_at: $entry->getCreatedAt()
        );
    }
}
```

**Step 3: Create StockEntryNotFoundException**

Create `backend/src/Exception/Stock/StockEntryNotFoundException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exception\Stock;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\Uid\Uuid;

class StockEntryNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Stock entry not found',
            type: 'STOCK_ENTRY_NOT_FOUND',
            code: 404,
            extraData: ['id' => (string) $id]
        ));
    }
}
```

**Step 4: Run code quality checks**

```bash
docker compose exec php vendor/bin/rector
mago format && mago lint && mago analyze
docker compose exec php vendor/bin/phpstan analyse
```

**Step 5: Commit**

```bash
git add backend/src/Service/StockEntryService.php backend/src/Exception/Stock/
git commit -s -m "feat(stock): add StockEntryService with add/consume/update operations"
```

---

## Task 7: Rewrite StockController

**Files:**
- Modify: `backend/src/Controller/Api/Internal/V1/StockController.php`

**Step 1: Rewrite StockController**

Replace contents of `backend/src/Controller/Api/Internal/V1/StockController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\V1;

use App\Request\AddStockRequest;
use App\Request\ConsumeStockRequest;
use App\Request\UpdateStockEntryRequest;
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
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: ProductSummaryResponse::class))),
        new OA\Property(property: 'meta', properties: [new OA\Property(property: 'total', type: 'integer')], type: 'object')
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
    #[OA\Get(
        summary: 'List stock entries',
        description: 'Returns individual stock entries with optional filtering.'
    )]
    #[OA\Parameter(name: 'location', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'product', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'List of entries', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: StockEntryResponse::class))),
        new OA\Property(property: 'meta', properties: [new OA\Property(property: 'total', type: 'integer')], type: 'object')
    ]))]
    public function listEntries(Request $request): JsonResponse
    {
        $locationId = $request->query->get('location');
        $productId = $request->query->get('product');

        $data = $this->stockEntryService->getEntries(
            locationId: $locationId !== null ? Uuid::fromString($locationId) : null,
            productId: $productId !== null ? Uuid::fromString($productId) : null
        );

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/stocks/entries/{uuid}', name: 'api_internal_v1_stocks_entries_show', requirements: ['uuid' => Requirement::UUID_V7], methods: ['GET'])]
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
    #[OA\Parameter(name: 'days', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 7))]
    #[OA\Response(response: 200, description: 'Expiring entries', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: ExpiringEntryResponse::class))),
        new OA\Property(property: 'meta', properties: [new OA\Property(property: 'total', type: 'integer')], type: 'object')
    ]))]
    public function expiring(Request $request): JsonResponse
    {
        $days = $request->query->getInt('days', 7);
        $data = $this->stockEntryService->getExpiringEntries($days);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/stocks/add', name: 'api_internal_v1_stocks_add', methods: ['POST'])]
    #[OA\Post(summary: 'Add stock', description: 'Creates N stock entries (one per unit).')]
    #[OA\RequestBody(required: true, content: new Model(type: AddStockRequest::class))]
    #[OA\Response(response: 201, description: 'Entries created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'created', type: 'integer'),
            new OA\Property(property: 'entries', type: 'array', items: new OA\Items(ref: new Model(type: StockEntryResponse::class)))
        ], type: 'object')
    ]))]
    #[OA\Response(response: 404, description: 'Product or location not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function add(#[MapRequestPayload] AddStockRequest $request): JsonResponse
    {
        $entries = $this->stockEntryService->addStock($request);

        return $this->json([
            'data' => [
                'created' => count($entries),
                'entries' => array_map(fn($e) => [
                    'id' => $e->getId(),
                    'best_before' => $e->getBestBefore()?->format('Y-m-d')
                ], $entries)
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/stocks/consume', name: 'api_internal_v1_stocks_consume', methods: ['POST'])]
    #[OA\Post(summary: 'Consume stock', description: 'Deletes N entries in FIFO order from specified location.')]
    #[OA\RequestBody(required: true, content: new Model(type: ConsumeStockRequest::class))]
    #[OA\Response(response: 200, description: 'Consumption result', content: new Model(type: ConsumeResultResponse::class))]
    #[OA\Response(response: 400, description: 'Insufficient stock')]
    #[OA\Response(response: 404, description: 'Product or location not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function consume(#[MapRequestPayload] ConsumeStockRequest $request): JsonResponse
    {
        $result = $this->stockEntryService->consumeStock($request);

        return $this->json(['data' => $result]);
    }

    #[Route('/stocks/entries/{uuid}', name: 'api_internal_v1_stocks_entries_update', requirements: ['uuid' => Requirement::UUID_V7], methods: ['PATCH'])]
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

    #[Route('/stocks/entries/{uuid}', name: 'api_internal_v1_stocks_entries_delete', requirements: ['uuid' => Requirement::UUID_V7], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Delete stock entry', description: 'Removes a single stock entry.')]
    #[OA\Response(response: 204, description: 'Entry deleted')]
    #[OA\Response(response: 404, description: 'Entry not found')]
    public function deleteEntry(Uuid $uuid): JsonResponse
    {
        $this->stockEntryService->deleteEntry($uuid);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
```

**Step 2: Run code quality checks**

```bash
docker compose exec php vendor/bin/rector
mago format && mago lint && mago analyze
docker compose exec php vendor/bin/phpstan analyse
```

**Step 3: Commit**

```bash
git add backend/src/Controller/Api/Internal/V1/StockController.php
git commit -s -m "feat(stock): rewrite StockController for entry-based API"
```

---

## Task 8: Add apiPatch to ApiTestTrait

**Files:**
- Modify: `backend/tests/Functional/Trait/ApiTestTrait.php`

**Step 1: Add apiPatch method**

Add after `apiPut` method in `backend/tests/Functional/Trait/ApiTestTrait.php`:

```php
/** @param array<string, mixed> $data */
protected function apiPatch(string $uri, array $data): Response
{
    $this->client->request(
        'PATCH',
        self::API_PREFIX . $uri,
        [],
        [],
        self::JSON_HEADERS,
        json_encode($data, JSON_THROW_ON_ERROR)
    );

    return $this->client->getResponse();
}
```

**Step 2: Commit**

```bash
git add backend/tests/Functional/Trait/ApiTestTrait.php
git commit -s -m "test: add apiPatch method to ApiTestTrait"
```

---

## Task 9: Create StockEntryFactory

**Files:**
- Create: `backend/src/Factory/StockEntryFactory.php`

**Step 1: Create StockEntryFactory**

Create `backend/src/Factory/StockEntryFactory.php`:

```php
<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\StockEntry;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<StockEntry>
 */
final class StockEntryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return StockEntry::class;
    }

    /** @return array<string, mixed> */
    protected function defaults(): array
    {
        return [
            'product' => ProductFactory::new(),
            'location' => LocationFactory::new(),
            'bestBefore' => self::faker()->optional(0.7)->dateTimeBetween('now', '+30 days')
        ];
    }
}
```

**Step 2: Run code quality checks**

```bash
docker compose exec php vendor/bin/rector
mago format && mago lint && mago analyze
docker compose exec php vendor/bin/phpstan analyse
```

**Step 3: Commit**

```bash
git add backend/src/Factory/StockEntryFactory.php
git commit -s -m "test(stock): add StockEntryFactory for testing"
```

---

## Task 10: Write StockController integration tests

**Files:**
- Rewrite: `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php`

**Step 1: Rewrite test file**

Replace contents of `backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Category;
use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Factory\CategoryFactory;
use App\Factory\LocationFactory;
use App\Factory\ProductFactory;
use App\Factory\StockEntryFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class StockControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function createCategory(array $attributes = []): Category
    {
        return CategoryFactory::createOne($attributes);
    }

    private function createLocation(array $attributes = []): Location
    {
        return LocationFactory::createOne($attributes);
    }

    private function createProduct(array $attributes = []): Product
    {
        return ProductFactory::createOne($attributes);
    }

    private function createStockEntry(array $attributes = []): StockEntry
    {
        return StockEntryFactory::createOne($attributes);
    }

    // ========== GET /stocks (Summary) ==========

    public function testSummaryReturnsEmptyArray(): void
    {
        $response = $this->apiGet('/stocks');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 0);
    }

    public function testSummaryReturnsAggregatedData(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $location = $this->createLocation(['name' => 'Fridge']);
        $product = $this->createProduct([
            'name' => 'Milk',
            'category' => $category,
            'defaultLocation' => $location,
            'unit' => 'carton'
        ]);

        // Create 3 entries
        $this->createStockEntry(['product' => $product, 'location' => $location, 'bestBefore' => new \DateTimeImmutable('+5 days')]);
        $this->createStockEntry(['product' => $product, 'location' => $location, 'bestBefore' => new \DateTimeImmutable('+10 days')]);
        $this->createStockEntry(['product' => $product, 'location' => $location, 'bestBefore' => null]);

        $response = $this->apiGet('/stocks');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame('Milk', $data['data'][0]['product']['name']);
        static::assertSame('carton', $data['data'][0]['product']['unit']);
        static::assertSame(3, $data['data'][0]['total_quantity']);
    }

    public function testSummaryFiltersLowStock(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $location = $this->createLocation(['name' => 'Fridge']);

        $productLow = $this->createProduct([
            'name' => 'Milk',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5
        ]);
        $productOk = $this->createProduct([
            'name' => 'Yogurt',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 2
        ]);

        // Milk: 2 entries (below minStock of 5)
        $this->createStockEntry(['product' => $productLow, 'location' => $location]);
        $this->createStockEntry(['product' => $productLow, 'location' => $location]);

        // Yogurt: 3 entries (above minStock of 2)
        $this->createStockEntry(['product' => $productOk, 'location' => $location]);
        $this->createStockEntry(['product' => $productOk, 'location' => $location]);
        $this->createStockEntry(['product' => $productOk, 'location' => $location]);

        $response = $this->apiGet('/stocks', ['low_stock' => 'true']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
        static::assertSame('Milk', $data['data'][0]['product']['name']);
    }

    // ========== GET /stocks/entries ==========

    public function testListEntriesReturnsAll(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $location = $this->createLocation(['name' => 'Fridge']);
        $product = $this->createProduct(['name' => 'Milk', 'category' => $category, 'defaultLocation' => $location]);

        $this->createStockEntry(['product' => $product, 'location' => $location]);
        $this->createStockEntry(['product' => $product, 'location' => $location]);

        $response = $this->apiGet('/stocks/entries');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListEntriesFiltersByLocation(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $fridge = $this->createLocation(['name' => 'Fridge']);
        $pantry = $this->createLocation(['name' => 'Pantry']);
        $product = $this->createProduct(['name' => 'Milk', 'category' => $category, 'defaultLocation' => $fridge]);

        $this->createStockEntry(['product' => $product, 'location' => $fridge]);
        $this->createStockEntry(['product' => $product, 'location' => $fridge]);
        $this->createStockEntry(['product' => $product, 'location' => $pantry]);

        $response = $this->apiGet('/stocks/entries', ['location' => (string) $fridge->getId()]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListEntriesFiltersByProduct(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $location = $this->createLocation(['name' => 'Fridge']);
        $milk = $this->createProduct(['name' => 'Milk', 'category' => $category, 'defaultLocation' => $location]);
        $yogurt = $this->createProduct(['name' => 'Yogurt', 'category' => $category, 'defaultLocation' => $location]);

        $this->createStockEntry(['product' => $milk, 'location' => $location]);
        $this->createStockEntry(['product' => $milk, 'location' => $location]);
        $this->createStockEntry(['product' => $yogurt, 'location' => $location]);

        $response = $this->apiGet('/stocks/entries', ['product' => (string) $milk->getId()]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    // ========== POST /stocks/add ==========

    public function testAddCreatesMultipleEntries(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $location = $this->createLocation(['name' => 'Fridge']);
        $product = $this->createProduct(['name' => 'Milk', 'category' => $category, 'defaultLocation' => $location]);

        $response = $this->apiPost('/stocks/add', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 3,
            'best_before' => '2026-02-15'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame(3, $data['data']['created']);
        static::assertCount(3, $data['data']['entries']);
        static::assertSame('2026-02-15', $data['data']['entries'][0]['best_before']);
    }

    public function testAddUsesDefaultExpiryDays(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $location = $this->createLocation(['name' => 'Fridge']);
        $product = $this->createProduct([
            'name' => 'Milk',
            'category' => $category,
            'defaultLocation' => $location,
            'defaultExpiryDays' => 14
        ]);

        $response = $this->apiPost('/stocks/add', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 1
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        $expectedDate = (new \DateTimeImmutable())->modify('+14 days')->format('Y-m-d');
        static::assertSame($expectedDate, $data['data']['entries'][0]['best_before']);
    }

    public function testAddWithoutExpiryLeavesNull(): void
    {
        $category = $this->createCategory(['name' => 'Pantry']);
        $location = $this->createLocation(['name' => 'Pantry']);
        $product = $this->createProduct([
            'name' => 'Pasta',
            'category' => $category,
            'defaultLocation' => $location,
            'defaultExpiryDays' => null
        ]);

        $response = $this->apiPost('/stocks/add', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 1
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertNull($data['data']['entries'][0]['best_before']);
    }

    public function testAddFailsWithInvalidProduct(): void
    {
        $location = $this->createLocation(['name' => 'Fridge']);

        $response = $this->apiPost('/stocks/add', [
            'product_id' => '01936f00-0000-7000-8000-000000000000',
            'location_id' => (string) $location->getId(),
            'quantity' => 1
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('PRODUCT_NOT_FOUND', $data['type']);
    }

    // ========== POST /stocks/consume ==========

    public function testConsumeDeletesEntriesInFifoOrder(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $location = $this->createLocation(['name' => 'Fridge']);
        $product = $this->createProduct(['name' => 'Milk', 'category' => $category, 'defaultLocation' => $location]);

        // Create entries with different best_before dates
        $entry1 = $this->createStockEntry(['product' => $product, 'location' => $location, 'bestBefore' => new \DateTimeImmutable('+5 days')]);
        $entry2 = $this->createStockEntry(['product' => $product, 'location' => $location, 'bestBefore' => new \DateTimeImmutable('+10 days')]);
        $entry3 = $this->createStockEntry(['product' => $product, 'location' => $location, 'bestBefore' => null]);

        $response = $this->apiPost('/stocks/consume', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 2
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(2, $data['data']['consumed']);
        static::assertCount(2, $data['data']['deleted_entries']);
        static::assertSame(1, $data['data']['remaining_at_location']);

        // Verify FIFO: entry1 (earliest) and entry2 should be deleted, entry3 (null) remains
        static::assertContains((string) $entry1->getId(), $data['data']['deleted_entries']);
        static::assertContains((string) $entry2->getId(), $data['data']['deleted_entries']);
    }

    public function testConsumeFailsWithInsufficientStock(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $location = $this->createLocation(['name' => 'Fridge']);
        $product = $this->createProduct(['name' => 'Milk', 'category' => $category, 'defaultLocation' => $location]);

        $this->createStockEntry(['product' => $product, 'location' => $location]);
        $this->createStockEntry(['product' => $product, 'location' => $location]);

        $response = $this->apiPost('/stocks/consume', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 5
        ]);
        $data = static::assertErrorResponse($response, Response::HTTP_BAD_REQUEST);

        static::assertSame('INSUFFICIENT_STOCK', $data['type']);
        static::assertSame(5, $data['requested']);
        static::assertSame(2, $data['available']);
    }

    public function testConsumeExactAvailableQuantity(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $location = $this->createLocation(['name' => 'Fridge']);
        $product = $this->createProduct(['name' => 'Milk', 'category' => $category, 'defaultLocation' => $location]);

        $this->createStockEntry(['product' => $product, 'location' => $location]);
        $this->createStockEntry(['product' => $product, 'location' => $location]);

        $response = $this->apiPost('/stocks/consume', [
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'quantity' => 2
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame(2, $data['data']['consumed']);
        static::assertSame(0, $data['data']['remaining_at_location']);
    }

    // ========== PATCH /stocks/entries/{id} ==========

    public function testUpdateEntryLocation(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $fridge = $this->createLocation(['name' => 'Fridge']);
        $pantry = $this->createLocation(['name' => 'Pantry']);
        $product = $this->createProduct(['name' => 'Milk', 'category' => $category, 'defaultLocation' => $fridge]);

        $entry = $this->createStockEntry(['product' => $product, 'location' => $fridge]);

        $response = $this->apiPatch('/stocks/entries/' . $entry->getId(), [
            'location_id' => (string) $pantry->getId()
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame((string) $pantry->getId(), $data['data']['location']['id']);
    }

    public function testUpdateEntryBestBefore(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $location = $this->createLocation(['name' => 'Fridge']);
        $product = $this->createProduct(['name' => 'Milk', 'category' => $category, 'defaultLocation' => $location]);

        $entry = $this->createStockEntry(['product' => $product, 'location' => $location, 'bestBefore' => new \DateTimeImmutable('+5 days')]);

        $response = $this->apiPatch('/stocks/entries/' . $entry->getId(), [
            'best_before' => '2026-03-01'
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('2026-03-01', $data['data']['best_before']);
    }

    // ========== DELETE /stocks/entries/{id} ==========

    public function testDeleteEntry(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $location = $this->createLocation(['name' => 'Fridge']);
        $product = $this->createProduct(['name' => 'Milk', 'category' => $category, 'defaultLocation' => $location]);

        $entry = $this->createStockEntry(['product' => $product, 'location' => $location]);

        $response = $this->apiDelete('/stocks/entries/' . $entry->getId());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testDeleteEntryNotFound(): void
    {
        $response = $this->apiDelete('/stocks/entries/01936f00-0000-7000-8000-000000000000');
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('STOCK_ENTRY_NOT_FOUND', $data['type']);
    }

    // ========== GET /stocks/expiring ==========

    public function testExpiringReturnsEntriesOrderedByUrgency(): void
    {
        $category = $this->createCategory(['name' => 'Dairy']);
        $location = $this->createLocation(['name' => 'Fridge']);
        $product = $this->createProduct(['name' => 'Milk', 'category' => $category, 'defaultLocation' => $location]);

        // Already expired
        $this->createStockEntry(['product' => $product, 'location' => $location, 'bestBefore' => new \DateTimeImmutable('-2 days')]);
        // Expiring soon
        $this->createStockEntry(['product' => $product, 'location' => $location, 'bestBefore' => new \DateTimeImmutable('+3 days')]);
        // Far future (should not appear with days=7)
        $this->createStockEntry(['product' => $product, 'location' => $location, 'bestBefore' => new \DateTimeImmutable('+30 days')]);
        // No expiry (should not appear)
        $this->createStockEntry(['product' => $product, 'location' => $location, 'bestBefore' => null]);

        $response = $this->apiGet('/stocks/expiring', ['days' => '7']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
        // First should be the expired one (negative days)
        static::assertLessThan(0, $data['data'][0]['days_until_expiry']);
        // Second should be expiring soon
        static::assertGreaterThan(0, $data['data'][1]['days_until_expiry']);
    }
}
```

**Step 2: Run tests**

```bash
docker compose exec php bin/phpunit tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php
```

Expected: All tests pass.

**Step 3: Run full test suite**

```bash
docker compose exec php bin/phpunit
```

**Step 4: Commit**

```bash
git add backend/tests/Functional/Controller/Api/Internal/V1/StockControllerTest.php
git commit -s -m "test(stock): rewrite StockController tests for entry-based API"
```

---

## Task 11: Delete old stock files

**Files to delete:**
- `backend/src/Entity/Stock.php`
- `backend/src/Entity/StockMovement.php`
- `backend/src/Entity/StockMovementType.php`
- `backend/src/Entity/StockEventType.php` (untracked, if exists)
- `backend/src/Repository/StockRepository.php`
- `backend/src/Repository/StockMovementRepository.php`
- `backend/src/Service/StockService.php`
- `backend/src/Request/CreateStockMovementRequest.php`
- `backend/src/Response/Stock/StockResponse.php`
- `backend/src/Response/Stock/StockMovementResponse.php`
- `backend/src/Response/Stock/StockLocationResponse.php`
- `backend/src/Factory/StockFactory.php`
- `backend/src/Factory/StockMovementFactory.php`

**Step 1: Delete old entity files**

```bash
rm backend/src/Entity/Stock.php backend/src/Entity/StockMovement.php backend/src/Entity/StockMovementType.php
rm -f backend/src/Entity/StockEventType.php
```

**Step 2: Delete old repository files**

```bash
rm backend/src/Repository/StockRepository.php backend/src/Repository/StockMovementRepository.php
```

**Step 3: Delete old service file**

```bash
rm backend/src/Service/StockService.php
```

**Step 4: Delete old request/response files**

```bash
rm backend/src/Request/CreateStockMovementRequest.php
rm backend/src/Response/Stock/StockResponse.php backend/src/Response/Stock/StockMovementResponse.php backend/src/Response/Stock/StockLocationResponse.php
```

**Step 5: Delete old factory files**

```bash
rm backend/src/Factory/StockFactory.php backend/src/Factory/StockMovementFactory.php
```

**Step 6: Run code quality checks**

```bash
docker compose exec php vendor/bin/rector
mago format && mago lint && mago analyze
docker compose exec php vendor/bin/phpstan analyse
```

**Step 7: Run tests to verify nothing breaks**

```bash
docker compose exec php bin/phpunit
```

**Step 8: Commit**

```bash
git add -A
git commit -s -m "refactor(stock): remove old aggregate-based stock entities and files"
```

---

## Task 12: Create migration to drop old tables

**Files:**
- Create: `backend/migrations/VersionXXXX_DropOldStockTables.php` (auto-generated)

**Step 1: Generate migration**

```bash
docker compose exec php bin/console doctrine:migrations:diff
```

**Step 2: Review migration**

Verify it contains:
- `DROP TABLE stock_movements`
- `DROP TABLE stocks`

**Step 3: Run migration**

```bash
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

**Step 4: Validate schema**

```bash
docker compose exec php bin/console doctrine:schema:validate
```

**Step 5: Commit**

```bash
git add backend/migrations/
git commit -s -m "feat(stock): add migration to drop old stocks and stock_movements tables"
```

---

## Task 13: Final verification

**Step 1: Run all quality checks**

```bash
docker compose exec php vendor/bin/rector
mago format && mago lint && mago analyze
docker compose exec php vendor/bin/phpstan analyse
```

**Step 2: Run full test suite**

```bash
docker compose exec php bin/phpunit
```

**Step 3: Validate schema**

```bash
docker compose exec php bin/console doctrine:schema:validate
```

**Step 4: Manual API testing (optional)**

```bash
# Add 3 milk cartons
curl -X POST https://localhost/api/internal/v1/stocks/add \
  -H "Content-Type: application/json" \
  -d '{"product_id": "<uuid>", "location_id": "<uuid>", "quantity": 3}'

# Get summary
curl https://localhost/api/internal/v1/stocks

# Consume 2
curl -X POST https://localhost/api/internal/v1/stocks/consume \
  -H "Content-Type: application/json" \
  -d '{"product_id": "<uuid>", "location_id": "<uuid>", "quantity": 2}'

# Check expiring
curl https://localhost/api/internal/v1/stocks/expiring?days=7
```

**Step 5: Commit any remaining changes**

```bash
git status
# If any uncommitted changes:
git add -A
git commit -s -m "chore(stock): final cleanup"
```

---

## Summary

**Total tasks:** 13

**Files created:** 15
- Entity: StockEntry
- Repository: StockEntryRepository
- Service: StockEntryService
- Requests: AddStockRequest, ConsumeStockRequest, UpdateStockEntryRequest
- Responses: StockEntryResponse, ProductBriefResponse, ProductSummaryResponse, LocationQuantityResponse, ConsumeResultResponse, ExpiringEntryResponse
- Factory: StockEntryFactory
- Exception: StockEntryNotFoundException

**Files modified:** 5
- Product entity (unit field)
- CreateProductRequest, UpdateProductRequest, ProductResponse (unit)
- StockController (rewritten)
- ApiTestTrait (apiPatch)
- InsufficientStockException (message)

**Files deleted:** 13
- Old entities, repositories, service, requests, responses, factories

**Migrations:** 2
- Add stock_entries table and product.unit
- Drop old stocks and stock_movements tables
