# Stock Management Backend Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement backend API for stock management - tracking inventory quantities per product/location with movement history.

**Architecture:** Two entities (Stock, StockMovement) with denormalized quantity on Stock for fast reads. Movements (ADD/REMOVE/ADJUST) modify Stock.quantity. Service layer handles business logic, controller exposes REST endpoints.

**Tech Stack:** Symfony 8, Doctrine ORM, PHP 8.4, PostgreSQL, PHPUnit 12, Foundry for factories

**Design Document:** `docs/plans/2026-01-18-stock-management-design.md`

**Working Directory:** `/home/pavel/projects/hestia/.worktrees/feat-stock-backend/backend`

---

## Task 1: Create Stock Entity

**Files:**
- Create: `src/Entity/Stock.php`
- Create: `src/Repository/StockRepository.php`

**Step 1: Create Stock entity**

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: StockRepository::class)]
#[ORM\Table(name: 'stocks')]
#[ORM\UniqueConstraint(name: 'stock_product_location_unique', columns: ['product_id', 'location_id'])]
#[ORM\Index(name: 'stock_product_idx', columns: ['product_id'])]
#[ORM\Index(name: 'stock_location_idx', columns: ['location_id'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['product', 'location'], message: 'Stock entry already exists for this product and location')]
class Stock
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Location $location;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $quantity = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, StockMovement> */
    #[ORM\OneToMany(targetEntity: StockMovement::class, mappedBy: 'stock', orphanRemoval: true)]
    private Collection $movements;

    public function __construct()
    {
        $this->movements = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function setLocation(Location $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, StockMovement> */
    public function getMovements(): Collection
    {
        return $this->movements;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```

**Step 2: Create StockRepository**

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    public function findByProductAndLocation(Uuid $productId, Uuid $locationId): ?Stock
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.product = :productId')
            ->andWhere('s.location = :locationId')
            ->setParameter('productId', $productId)
            ->setParameter('locationId', $locationId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Stock[]
     */
    public function findByFilters(?Uuid $locationId, bool $lowStockOnly): array
    {
        $qb = $this->createQueryBuilder('s')
            ->join('s.product', 'p')
            ->join('s.location', 'l')
            ->addSelect('p', 'l');

        if ($locationId !== null) {
            $qb->andWhere('s.location = :locationId')
                ->setParameter('locationId', $locationId);
        }

        if ($lowStockOnly) {
            $qb->andWhere('s.quantity < p.minStock');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<array{location_id: string, location_name: string, quantity: int}>
     */
    public function getStockSummaryForProduct(Uuid $productId): array
    {
        return $this->createQueryBuilder('s')
            ->select('IDENTITY(s.location) as location_id', 'l.name as location_name', 's.quantity')
            ->join('s.location', 'l')
            ->andWhere('s.product = :productId')
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getArrayResult();
    }
}
```

**Step 3: Verify syntax**

Run: `docker compose exec php php -l src/Entity/Stock.php && docker compose exec php php -l src/Repository/StockRepository.php`
Expected: No syntax errors

**Step 4: Commit**

```bash
git add src/Entity/Stock.php src/Repository/StockRepository.php
git commit -s -m "feat(stock): add Stock entity and repository"
```

---

## Task 2: Create StockMovement Entity

**Files:**
- Create: `src/Entity/StockMovementType.php` (enum)
- Create: `src/Entity/StockMovement.php`
- Create: `src/Repository/StockMovementRepository.php`

**Step 1: Create StockMovementType enum**

```php
<?php

declare(strict_types=1);

namespace App\Entity;

enum StockMovementType: string
{
    case ADD = 'ADD';
    case REMOVE = 'REMOVE';
    case ADJUST = 'ADJUST';
}
```

**Step 2: Create StockMovement entity**

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StockMovementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: StockMovementRepository::class)]
#[ORM\Table(name: 'stock_movements')]
#[ORM\Index(name: 'stock_movement_stock_idx', columns: ['stock_id'])]
class StockMovement
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Stock::class, inversedBy: 'movements')]
    #[ORM\JoinColumn(nullable: false)]
    private Stock $stock;

    #[ORM\Column(type: 'string', enumType: StockMovementType::class)]
    private StockMovementType $type;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getStock(): Stock
    {
        return $this->stock;
    }

    public function setStock(Stock $stock): self
    {
        $this->stock = $stock;
        return $this;
    }

    public function getType(): StockMovementType
    {
        return $this->type;
    }

    public function setType(StockMovementType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
```

**Step 3: Create StockMovementRepository**

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockMovement>
 */
class StockMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMovement::class);
    }
}
```

**Step 4: Verify syntax**

Run: `docker compose exec php php -l src/Entity/StockMovementType.php && docker compose exec php php -l src/Entity/StockMovement.php && docker compose exec php php -l src/Repository/StockMovementRepository.php`
Expected: No syntax errors

**Step 5: Commit**

```bash
git add src/Entity/StockMovementType.php src/Entity/StockMovement.php src/Repository/StockMovementRepository.php
git commit -s -m "feat(stock): add StockMovement entity and repository"
```

---

## Task 3: Create Database Migration

**Files:**
- Create: `migrations/VersionXXXX.php` (generated)

**Step 1: Generate migration**

Run: `docker compose exec php bin/console doctrine:migrations:diff`
Expected: Migration file created

**Step 2: Review generated migration**

Open the generated migration file and verify it contains:
- `stocks` table with correct columns and indexes
- `stock_movements` table with correct columns
- Foreign key constraints

**Step 3: Run migration**

Run: `docker compose exec php bin/console doctrine:migrations:migrate --no-interaction`
Expected: Migration executed successfully

**Step 4: Verify tables exist**

Run: `docker compose exec php bin/console doctrine:schema:validate`
Expected: Schema is in sync

**Step 5: Commit**

```bash
git add migrations/
git commit -s -m "feat(stock): add database migration for stock tables"
```

---

## Task 4: Create InsufficientStockException

**Files:**
- Create: `src/Exception/Stock/InsufficientStockException.php`

**Step 1: Create exception class**

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
        parent::__construct(
            new ApiProblem(
                title: 'Insufficient stock',
                type: 'INSUFFICIENT_STOCK',
                code: 400,
                extraData: [
                    'requested' => $requested,
                    'available' => $available,
                ],
            ),
        );
    }
}
```

**Step 2: Verify syntax**

Run: `docker compose exec php php -l src/Exception/Stock/InsufficientStockException.php`
Expected: No syntax errors

**Step 3: Commit**

```bash
git add src/Exception/Stock/InsufficientStockException.php
git commit -s -m "feat(stock): add InsufficientStockException"
```

---

## Task 5: Create Request DTO

**Files:**
- Create: `src/Request/CreateStockMovementRequest.php`

**Step 1: Create request DTO**

```php
<?php

declare(strict_types=1);

namespace App\Request;

use App\Entity\StockMovementType;
use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateStockMovementRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Product ID is required')]
        #[Assert\Uuid(message: 'Product ID must be a valid UUID')]
        public string $product_id,

        #[Assert\NotBlank(message: 'Location ID is required')]
        #[Assert\Uuid(message: 'Location ID must be a valid UUID')]
        public string $location_id,

        #[Assert\NotBlank(message: 'Movement type is required')]
        #[Assert\Choice(callback: [StockMovementType::class, 'cases'], message: 'Invalid movement type')]
        public string $type,

        #[Assert\NotNull(message: 'Quantity is required')]
        #[Assert\Positive(message: 'Quantity must be positive')]
        public int $quantity,

        #[Assert\Length(max: 255, maxMessage: 'Notes cannot exceed 255 characters')]
        public ?string $notes = null,
    ) {}
}
```

**Step 2: Verify syntax**

Run: `docker compose exec php php -l src/Request/CreateStockMovementRequest.php`
Expected: No syntax errors

**Step 3: Commit**

```bash
git add src/Request/CreateStockMovementRequest.php
git commit -s -m "feat(stock): add CreateStockMovementRequest DTO"
```

---

## Task 6: Create Response DTOs

**Files:**
- Create: `src/Response/Stock/StockResponse.php`
- Create: `src/Response/Stock/StockMovementResponse.php`
- Create: `src/Response/Stock/StockSummaryResponse.php`
- Create: `src/Response/Stock/StockLocationResponse.php`

**Step 1: Create StockResponse**

```php
<?php

declare(strict_types=1);

namespace App\Response\Stock;

use App\Entity\Stock;
use App\Response\Product\ProductResponse;
use App\Response\Location\LocationResponse;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Stock::class)]
readonly class StockResponse
{
    public function __construct(
        public string $id,
        public ProductResponse $product,
        public LocationResponse $location,
        public int $quantity,
        public string $updated_at,
    ) {}
}
```

**Step 2: Create StockMovementResponse**

```php
<?php

declare(strict_types=1);

namespace App\Response\Stock;

use App\Entity\StockMovement;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: StockMovement::class)]
readonly class StockMovementResponse
{
    public function __construct(
        public string $id,
        public StockResponse $stock,
        public string $type,
        public int $quantity,
        public ?string $notes,
        public string $created_at,
    ) {}
}
```

**Step 3: Create StockLocationResponse (for stock summary)**

```php
<?php

declare(strict_types=1);

namespace App\Response\Stock;

readonly class StockLocationResponse
{
    public function __construct(
        public string $location_id,
        public string $location_name,
        public int $quantity,
    ) {}
}
```

**Step 4: Create StockSummaryResponse**

```php
<?php

declare(strict_types=1);

namespace App\Response\Stock;

readonly class StockSummaryResponse
{
    /**
     * @param StockLocationResponse[] $locations
     */
    public function __construct(
        public int $total_quantity,
        public array $locations,
    ) {}
}
```

**Step 5: Verify syntax**

Run: `docker compose exec php php -l src/Response/Stock/StockResponse.php && docker compose exec php php -l src/Response/Stock/StockMovementResponse.php && docker compose exec php php -l src/Response/Stock/StockLocationResponse.php && docker compose exec php php -l src/Response/Stock/StockSummaryResponse.php`
Expected: No syntax errors

**Step 6: Commit**

```bash
git add src/Response/Stock/
git commit -s -m "feat(stock): add response DTOs"
```

---

## Task 7: Create StockService

**Files:**
- Create: `src/Service/StockService.php`

**Step 1: Create service**

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Stock;
use App\Entity\StockMovement;
use App\Entity\StockMovementType;
use App\Exception\Product\ProductNotFoundException;
use App\Exception\Product\LocationNotFoundException;
use App\Exception\Stock\InsufficientStockException;
use App\Repository\ProductRepository;
use App\Repository\LocationRepository;
use App\Repository\StockRepository;
use App\Request\CreateStockMovementRequest;
use App\Response\Stock\StockLocationResponse;
use App\Response\Stock\StockSummaryResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class StockService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StockRepository $stockRepository,
        private ProductRepository $productRepository,
        private LocationRepository $locationRepository,
    ) {}

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
                quantity: $row['quantity'],
            );
        }

        return new StockSummaryResponse(
            total_quantity: $totalQuantity,
            locations: $locations,
        );
    }

    private function calculateNewQuantity(int $currentQuantity, StockMovementType $type, int $movementQuantity): int
    {
        return match ($type) {
            StockMovementType::ADD => $currentQuantity + $movementQuantity,
            StockMovementType::REMOVE => $currentQuantity - $movementQuantity,
            StockMovementType::ADJUST => $movementQuantity,
        };
    }
}
```

**Step 2: Verify syntax**

Run: `docker compose exec php php -l src/Service/StockService.php`
Expected: No syntax errors

**Step 3: Commit**

```bash
git add src/Service/StockService.php
git commit -s -m "feat(stock): add StockService with movement logic"
```

---

## Task 8: Create StockController

**Files:**
- Create: `src/Controller/Api/Internal/V1/StockController.php`

**Step 1: Create controller**

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\V1;

use App\Request\CreateStockMovementRequest;
use App\Response\Stock\StockMovementResponse;
use App\Response\Stock\StockResponse;
use App\Service\StockService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/internal/v1')]
#[OA\Tag(name: 'Stock')]
class StockController extends AbstractController
{
    public function __construct(
        private StockService $stockService,
        private ObjectMapperInterface $objectMapper,
    ) {}

    #[Route('/stocks', name: 'api_internal_v1_stocks_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List stock levels',
        description: 'Returns stock levels with optional filtering by location and low stock status',
    )]
    #[OA\Parameter(name: 'location', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'low_stock', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Response(response: 200, description: 'List of stock entries')]
    public function list(Request $request): JsonResponse
    {
        $locationId = $request->query->get('location');
        $lowStockOnly = $request->query->getBoolean('low_stock', false);

        $stocks = $this->stockService->listStocks($locationId, $lowStockOnly);

        $data = array_map(
            fn ($stock) => $this->objectMapper->map($stock, StockResponse::class),
            $stocks,
        );

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }

    #[Route('/stocks/movements', name: 'api_internal_v1_stocks_movements_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create stock movement',
        description: 'Creates a stock movement (ADD, REMOVE, or ADJUST) and updates stock quantity',
    )]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['product_id', 'location_id', 'type', 'quantity'],
        properties: [
            new OA\Property(property: 'product_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'location_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'type', type: 'string', enum: ['ADD', 'REMOVE', 'ADJUST']),
            new OA\Property(property: 'quantity', type: 'integer', minimum: 1),
            new OA\Property(property: 'notes', type: 'string', nullable: true),
        ],
    ))]
    #[OA\Response(response: 201, description: 'Movement created')]
    #[OA\Response(response: 400, description: 'Validation error or insufficient stock')]
    #[OA\Response(response: 404, description: 'Product or location not found')]
    public function createMovement(
        #[MapRequestPayload] CreateStockMovementRequest $request,
    ): JsonResponse {
        $movement = $this->stockService->createMovement($request);

        return $this->json(
            ['data' => $this->objectMapper->map($movement, StockMovementResponse::class)],
            Response::HTTP_CREATED,
        );
    }
}
```

**Step 2: Verify syntax**

Run: `docker compose exec php php -l src/Controller/Api/Internal/V1/StockController.php`
Expected: No syntax errors

**Step 3: Commit**

```bash
git add src/Controller/Api/Internal/V1/StockController.php
git commit -s -m "feat(stock): add StockController with list and createMovement endpoints"
```

---

## Task 9: Create Test Factories

**Files:**
- Create: `src/Factory/StockFactory.php`
- Create: `src/Factory/StockMovementFactory.php`

**Step 1: Create StockFactory**

```php
<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Stock;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Stock>
 */
final class StockFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Stock::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'product' => ProductFactory::new(),
            'location' => LocationFactory::new(),
            'quantity' => self::faker()->numberBetween(0, 100),
        ];
    }
}
```

**Step 2: Create StockMovementFactory**

```php
<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\StockMovement;
use App\Entity\StockMovementType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<StockMovement>
 */
final class StockMovementFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return StockMovement::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'stock' => StockFactory::new(),
            'type' => self::faker()->randomElement(StockMovementType::cases()),
            'quantity' => self::faker()->numberBetween(1, 50),
            'notes' => self::faker()->optional()->sentence(),
        ];
    }
}
```

**Step 3: Verify syntax**

Run: `docker compose exec php php -l src/Factory/StockFactory.php && docker compose exec php php -l src/Factory/StockMovementFactory.php`
Expected: No syntax errors

**Step 4: Commit**

```bash
git add src/Factory/StockFactory.php src/Factory/StockMovementFactory.php
git commit -s -m "feat(stock): add Foundry factories for Stock and StockMovement"
```

---

## Task 10: Write Controller Tests

**Files:**
- Create: `tests/Controller/Api/Internal/V1/StockControllerTest.php`

**Step 1: Create test file**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\Internal\V1;

use App\Factory\CategoryFactory;
use App\Factory\LocationFactory;
use App\Factory\ProductFactory;
use App\Factory\StockFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class StockControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testListStocksReturnsEmptyArray(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/internal/v1/stocks');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame([], $data['data']);
        $this->assertSame(0, $data['meta']['total']);
    }

    public function testListStocksReturnsStockEntries(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $location = LocationFactory::createOne(['name' => 'Kitchen']);
        $product = ProductFactory::createOne([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
        ]);
        StockFactory::createOne([
            'product' => $product,
            'location' => $location,
            'quantity' => 10,
        ]);

        $client->request('GET', '/api/internal/v1/stocks');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['data']);
        $this->assertSame(10, $data['data'][0]['quantity']);
        $this->assertSame('Test Product', $data['data'][0]['product']['name']);
    }

    public function testListStocksFiltersByLocation(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $location1 = LocationFactory::createOne(['name' => 'Kitchen']);
        $location2 = LocationFactory::createOne(['name' => 'Pantry']);
        $product = ProductFactory::createOne([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location1,
        ]);
        StockFactory::createOne(['product' => $product, 'location' => $location1, 'quantity' => 5]);
        StockFactory::createOne(['product' => $product, 'location' => $location2, 'quantity' => 3]);

        $client->request('GET', '/api/internal/v1/stocks?location=' . $location1->getId());

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['data']);
        $this->assertSame(5, $data['data'][0]['quantity']);
    }

    public function testListStocksFiltersLowStock(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $location = LocationFactory::createOne(['name' => 'Kitchen']);
        $productLow = ProductFactory::createOne([
            'name' => 'Low Stock Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 10,
        ]);
        $productOk = ProductFactory::createOne([
            'name' => 'OK Stock Product',
            'category' => $category,
            'defaultLocation' => $location,
            'minStock' => 5,
        ]);
        StockFactory::createOne(['product' => $productLow, 'location' => $location, 'quantity' => 3]);
        StockFactory::createOne(['product' => $productOk, 'location' => $location, 'quantity' => 10]);

        $client->request('GET', '/api/internal/v1/stocks?low_stock=true');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['data']);
        $this->assertSame('Low Stock Product', $data['data'][0]['product']['name']);
    }

    public function testCreateMovementAddCreatesStockAndMovement(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $location = LocationFactory::createOne(['name' => 'Kitchen']);
        $product = ProductFactory::createOne([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
        ]);

        $client->request('POST', '/api/internal/v1/stocks/movements', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'type' => 'ADD',
            'quantity' => 5,
            'notes' => 'Initial stock',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('ADD', $data['data']['type']);
        $this->assertSame(5, $data['data']['quantity']);
        $this->assertSame(5, $data['data']['stock']['quantity']);
        $this->assertSame('Initial stock', $data['data']['notes']);
    }

    public function testCreateMovementRemoveDecreasesQuantity(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $location = LocationFactory::createOne(['name' => 'Kitchen']);
        $product = ProductFactory::createOne([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
        ]);
        StockFactory::createOne(['product' => $product, 'location' => $location, 'quantity' => 10]);

        $client->request('POST', '/api/internal/v1/stocks/movements', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'type' => 'REMOVE',
            'quantity' => 3,
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(7, $data['data']['stock']['quantity']);
    }

    public function testCreateMovementAdjustSetsAbsoluteQuantity(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $location = LocationFactory::createOne(['name' => 'Kitchen']);
        $product = ProductFactory::createOne([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
        ]);
        StockFactory::createOne(['product' => $product, 'location' => $location, 'quantity' => 10]);

        $client->request('POST', '/api/internal/v1/stocks/movements', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'type' => 'ADJUST',
            'quantity' => 3,
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(3, $data['data']['stock']['quantity']);
    }

    public function testCreateMovementRemoveFailsWithInsufficientStock(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $location = LocationFactory::createOne(['name' => 'Kitchen']);
        $product = ProductFactory::createOne([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
        ]);
        StockFactory::createOne(['product' => $product, 'location' => $location, 'quantity' => 5]);

        $client->request('POST', '/api/internal/v1/stocks/movements', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'type' => 'REMOVE',
            'quantity' => 10,
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('INSUFFICIENT_STOCK', $data['type']);
    }

    public function testCreateMovementFailsWithInvalidProduct(): void
    {
        $client = static::createClient();

        $location = LocationFactory::createOne(['name' => 'Kitchen']);

        $client->request('POST', '/api/internal/v1/stocks/movements', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'product_id' => '01936f00-0000-7000-8000-000000000000',
            'location_id' => (string) $location->getId(),
            'type' => 'ADD',
            'quantity' => 5,
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateMovementFailsWithInvalidLocation(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $location = LocationFactory::createOne(['name' => 'Kitchen']);
        $product = ProductFactory::createOne([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
        ]);

        $client->request('POST', '/api/internal/v1/stocks/movements', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'product_id' => (string) $product->getId(),
            'location_id' => '01936f00-0000-7000-8000-000000000000',
            'type' => 'ADD',
            'quantity' => 5,
        ]));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateMovementFailsWithNegativeQuantity(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $location = LocationFactory::createOne(['name' => 'Kitchen']);
        $product = ProductFactory::createOne([
            'name' => 'Test Product',
            'category' => $category,
            'defaultLocation' => $location,
        ]);

        $client->request('POST', '/api/internal/v1/stocks/movements', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'product_id' => (string) $product->getId(),
            'location_id' => (string) $location->getId(),
            'type' => 'ADD',
            'quantity' => -5,
        ]));

        $this->assertResponseStatusCodeSame(422);
    }
}
```

**Step 2: Run tests to verify they fail (no implementation yet)**

Run: `docker compose exec php bin/phpunit tests/Controller/Api/Internal/V1/StockControllerTest.php`
Expected: Tests fail (routes/classes don't exist yet if running incrementally)

**Step 3: Commit**

```bash
git add tests/Controller/Api/Internal/V1/StockControllerTest.php
git commit -s -m "test(stock): add StockController integration tests"
```

---

## Task 11: Run All Tests and Fix Issues

**Step 1: Run PHPStan**

Run: `docker compose exec php vendor/bin/phpstan analyse`
Expected: No errors (fix any that appear)

**Step 2: Run Mago (format/lint)**

Run: `mago format src/Entity/Stock.php src/Entity/StockMovement.php src/Entity/StockMovementType.php src/Repository/StockRepository.php src/Repository/StockMovementRepository.php src/Service/StockService.php src/Controller/Api/Internal/V1/StockController.php src/Request/CreateStockMovementRequest.php src/Response/Stock/ src/Exception/Stock/ src/Factory/StockFactory.php src/Factory/StockMovementFactory.php && mago lint && mago analyze`
Expected: Files formatted, no lint errors

**Step 3: Run full test suite**

Run: `docker compose exec php bin/phpunit`
Expected: All tests pass

**Step 4: Commit any fixes**

```bash
git add -A
git commit -s -m "fix(stock): address static analysis and test issues"
```

---

## Task 12: Update ProductResponse with Stock Summary

**Files:**
- Modify: `src/Response/Product/ProductResponse.php`
- Modify: `src/Service/ProductService.php`

**Step 1: Add stock_summary field to ProductResponse**

Add to `ProductResponse.php`:
```php
public ?StockSummaryResponse $stock_summary = null,
```

**Step 2: Update ProductService to include stock summary**

Inject `StockService` and call `getStockSummaryForProduct()` when building response.

**Step 3: Run tests**

Run: `docker compose exec php bin/phpunit`
Expected: All tests pass

**Step 4: Commit**

```bash
git add src/Response/Product/ProductResponse.php src/Service/ProductService.php
git commit -s -m "feat(stock): add stock_summary to ProductResponse"
```

---

## Task 13: Final Verification

**Step 1: Run full test suite**

Run: `docker compose exec php bin/phpunit`
Expected: All tests pass (61 original + new stock tests)

**Step 2: Run static analysis**

Run: `docker compose exec php vendor/bin/phpstan analyse`
Expected: No errors

**Step 3: Run linter**

Run: `mago lint && mago analyze`
Expected: No errors

**Step 4: Verify API documentation**

Run: `docker compose up -d && curl -k https://localhost/api/doc.json | jq '.paths | keys'`
Expected: `/api/internal/v1/stocks` and `/api/internal/v1/stocks/movements` appear

**Step 5: Final commit if needed**

```bash
git status
# If any uncommitted changes:
git add -A
git commit -s -m "chore(stock): final cleanup"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Stock entity + repository | 2 files |
| 2 | StockMovement entity + enum + repository | 3 files |
| 3 | Database migration | 1 file |
| 4 | InsufficientStockException | 1 file |
| 5 | CreateStockMovementRequest DTO | 1 file |
| 6 | Response DTOs | 4 files |
| 7 | StockService | 1 file |
| 8 | StockController | 1 file |
| 9 | Test factories | 2 files |
| 10 | Controller tests | 1 file |
| 11 | Static analysis + fixes | - |
| 12 | ProductResponse update | 2 files |
| 13 | Final verification | - |

**Total: ~19 new files, ~13 commits**
