# W1 — Category & Location Service Extraction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move `Category` and `Location` CRUD out of their controllers into dedicated services, and replace the racy hand-rolled name-uniqueness check with a `UniqueConstraintViolationException`-at-flush translation so a duplicate name always returns 409 (never an unmapped 500).

**Architecture:** Two new concrete services (`CategoryService`, `LocationService`) mirroring the existing service convention (e.g. `ShoppingListService`, `ChoreService`). The DB unique constraint is the single source of truth for name uniqueness; the service catches `Doctrine\DBAL\Exception\UniqueConstraintViolationException` at `flush()` and rethrows the existing `*NameTakenException` (409). The pre-check `assertNameAvailable` is deleted. `#[UniqueEntity]` stays on the entities as documentation/secondary protection. Controllers become thin delegators. `usageCount` is a public service method the controller calls when building the `usage_count` response field.

**Tech Stack:** PHP 8.4, Symfony, Doctrine ORM/DBAL, PHPUnit, Zenstruck Foundry, mago/phpstan (`make lint`), `make test`.

**Spec:** `docs/superpowers/specs/2026-06-06-w1-category-location-service-extraction-design.md`

**Working directory for all commands:** `/home/pavel/projects/personal/hestia/backend`

---

## File Structure

| File | Responsibility | Action |
|------|----------------|--------|
| `src/Service/CategoryService.php` | Category CRUD + uniqueness translation + usageCount | Create |
| `src/Service/LocationService.php` | Location CRUD + uniqueness translation + usageCount | Create |
| `src/Controller/Api/Internal/V1/CategoryController.php` | HTTP delegation + response mapping only | Modify (rewrite bodies + ctor) |
| `src/Controller/Api/Internal/V1/LocationController.php` | HTTP delegation + response mapping only | Modify (rewrite bodies + ctor) |
| `tests/Unit/Service/CategoryServiceTest.php` | Isolated unit tests (mocked EM/repos) | Create |
| `tests/Unit/Service/LocationServiceTest.php` | Isolated unit tests (mocked EM/repos) | Create |
| `tests/Functional/Controller/Api/Internal/V1/CategoryControllerTest.php` | End-to-end contract (real DB) — safety net, must stay green | Unchanged |
| `tests/Functional/Controller/Api/Internal/V1/LocationControllerTest.php` | End-to-end contract (real DB) — safety net, must stay green | Unchanged |

**Convention reminders (from CLAUDE.md / memory):**
- Backend gate is **`make lint`** (runs rector → mago format → mago lint → mago analyze → phpstan). Run it before each commit; it **auto-fixes** files.
- **Stage explicitly** (`git add <paths>`) — never `git add -A` (avoids committing generated `config/reference.php`).
- Tests run inside Docker: `docker compose exec -T php bin/phpunit ...`. `make test` runs the full suite.
- Commits: `git commit -s -m "<type>(<scope>): <desc> (#55)"`.

---

## Task 1: CategoryService + unit tests

**Files:**
- Create: `tests/Unit/Service/CategoryServiceTest.php`
- Create: `src/Service/CategoryService.php`

- [ ] **Step 1: Write the failing unit test**

Create `tests/Unit/Service/CategoryServiceTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service;

use App\Entity\Category;
use App\Exception\Category\CategoryInUseException;
use App\Exception\Category\CategoryNameTakenException;
use App\Exception\Category\CategoryNotFoundException;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Request\CreateCategoryRequest;
use App\Request\UpdateCategoryRequest;
use App\Service\CategoryService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class CategoryServiceTest extends TestCase
{
    public function testCreateTranslatesUniqueViolationToNameTaken(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush')
            ->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        $service = new CategoryService(
            $em,
            $this->createStub(CategoryRepository::class),
            $this->createStub(ProductRepository::class)
        );

        $this->expectException(CategoryNameTakenException::class);
        $service->create(new CreateCategoryRequest('Снеки'));
    }

    public function testUpdateTranslatesUniqueViolationToNameTaken(): void
    {
        $existing = new Category();
        $existing->setName('Напитки');

        $repo = $this->createStub(CategoryRepository::class);
        $repo->method('find')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush')
            ->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        $service = new CategoryService($em, $repo, $this->createStub(ProductRepository::class));

        $this->expectException(CategoryNameTakenException::class);
        $service->update(Uuid::v7(), new UpdateCategoryRequest('Снеки'));
    }

    public function testUpdateWithSameNameDoesNotFlush(): void
    {
        $existing = new Category();
        $existing->setName('Снеки');

        $repo = $this->createStub(CategoryRepository::class);
        $repo->method('find')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $service = new CategoryService($em, $repo, $this->createStub(ProductRepository::class));

        $result = $service->update(Uuid::v7(), new UpdateCategoryRequest('Снеки'));
        static::assertSame('Снеки', $result->getName());
    }

    public function testUpdateMissingThrowsNotFound(): void
    {
        $repo = $this->createStub(CategoryRepository::class);
        $repo->method('find')->willReturn(null);

        $service = new CategoryService(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            $this->createStub(ProductRepository::class)
        );

        $this->expectException(CategoryNotFoundException::class);
        $service->update(Uuid::v7(), new UpdateCategoryRequest('Снеки'));
    }

    public function testDeleteInUseThrowsConflict(): void
    {
        $category = new Category();
        $category->setName('Снеки');

        $repo = $this->createStub(CategoryRepository::class);
        $repo->method('find')->willReturn($category);

        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(3);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');

        $service = new CategoryService($em, $repo, $products);

        $this->expectException(CategoryInUseException::class);
        $service->delete(Uuid::v7());
    }

    public function testDeleteEmptyRemovesAndFlushes(): void
    {
        $category = new Category();
        $category->setName('Снеки');

        $repo = $this->createStub(CategoryRepository::class);
        $repo->method('find')->willReturn($category);

        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($category);
        $em->expects($this->once())->method('flush');

        $service = new CategoryService($em, $repo, $products);
        $service->delete(Uuid::v7());
    }

    public function testUsageCountCountsProducts(): void
    {
        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(5);

        $service = new CategoryService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(CategoryRepository::class),
            $products
        );

        static::assertSame(5, $service->usageCount(new Category()));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec -T php bin/phpunit tests/Unit/Service/CategoryServiceTest.php`
Expected: FAIL — `Error: Class "App\Service\CategoryService" not found` (or autoload error).

- [ ] **Step 3: Write the minimal implementation**

Create `src/Service/CategoryService.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\Category;
use App\Exception\Category\CategoryInUseException;
use App\Exception\Category\CategoryNameTakenException;
use App\Exception\Category\CategoryNotFoundException;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Request\CreateCategoryRequest;
use App\Request\UpdateCategoryRequest;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

readonly class CategoryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository $categoryRepository,
        private ProductRepository $productRepository
    ) {
    }

    /** @return Category[] */
    public function list(): array
    {
        return $this->categoryRepository->findAllOrderedByName();
    }

    public function create(CreateCategoryRequest $request): Category
    {
        $category = new Category();
        $category->setName($request->name);

        $this->em->persist($category);
        $this->flushOrNameTaken($request->name);

        return $category;
    }

    public function update(Uuid $id, UpdateCategoryRequest $request): Category
    {
        $category = $this->categoryRepository->find($id) ?? throw new CategoryNotFoundException($id);

        if ($request->name !== $category->getName()) {
            $category->setName($request->name);
            $this->flushOrNameTaken($request->name);
        }

        return $category;
    }

    public function delete(Uuid $id): void
    {
        $category = $this->categoryRepository->find($id) ?? throw new CategoryNotFoundException($id);

        $usage = $this->usageCount($category);
        if ($usage > 0) {
            throw new CategoryInUseException($usage);
        }

        $this->em->remove($category);
        $this->em->flush();
    }

    public function usageCount(Category $category): int
    {
        return $this->productRepository->count(['category' => $category]);
    }

    /**
     * The DB unique constraint is the single authority for name uniqueness;
     * translate its violation (incl. the concurrent-create race) into a clean 409.
     */
    private function flushOrNameTaken(string $name): void
    {
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new CategoryNameTakenException($name);
        }
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose exec -T php bin/phpunit tests/Unit/Service/CategoryServiceTest.php`
Expected: PASS — 7 tests, 7 assertions (or more), OK.

- [ ] **Step 5: Lint, stage, commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint
git add src/Service/CategoryService.php tests/Unit/Service/CategoryServiceTest.php
git commit -s -m "fix(category): extract CategoryService, translate unique violation to 409 (#55)"
```

Expected: `make lint` ends with no errors (it may reformat the new files — that's fine; they're already staged after, re-add if it changed them).

---

## Task 2: Delegate CategoryController to CategoryService

**Files:**
- Modify: `src/Controller/Api/Internal/V1/CategoryController.php` (full rewrite — bodies, constructor, imports)
- Safety net: `tests/Functional/Controller/Api/Internal/V1/CategoryControllerTest.php` (unchanged, must stay green)

- [ ] **Step 1: Rewrite the controller**

Replace the entire contents of `src/Controller/Api/Internal/V1/CategoryController.php` with:

```php
<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Entity\Category;
use App\Request\CreateCategoryRequest;
use App\Request\UpdateCategoryRequest;
use App\Response\Category\CategoryListItemResponse;
use App\Service\CategoryService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {
    }

    #[Route('/categories', name: 'api_categories_list', methods: ['GET'])]
    #[OA\Get(description: 'Returns all product categories with usage counts.', summary: 'List categories')]
    #[OA\Response(response: 200, description: 'List of categories', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: CategoryListItemResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function list(): JsonResponse
    {
        $data = array_map($this->toResponse(...), $this->categoryService->list());

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/categories', name: 'api_categories_create', methods: ['POST'])]
    #[OA\Post(summary: 'Create category', description: 'Creates a product category.')]
    #[OA\RequestBody(required: true, content: new Model(type: CreateCategoryRequest::class))]
    #[OA\Response(response: 201, description: 'Category created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: CategoryListItemResponse::class))
    ]))]
    #[OA\Response(response: 409, description: 'Name already exists')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function create(#[MapRequestPayload] CreateCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request);

        return $this->json(['data' => $this->toResponse($category)], Response::HTTP_CREATED);
    }

    #[Route(
        '/categories/{uuid}',
        name: 'api_categories_update',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['PATCH']
    )]
    #[OA\Patch(summary: 'Rename category', description: 'Renames a product category.')]
    #[OA\RequestBody(required: true, content: new Model(type: UpdateCategoryRequest::class))]
    #[OA\Response(response: 200, description: 'Category updated', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: CategoryListItemResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Category not found')]
    #[OA\Response(response: 409, description: 'Name already exists')]
    public function update(Uuid $uuid, #[MapRequestPayload] UpdateCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->update($uuid, $request);

        return $this->json(['data' => $this->toResponse($category)]);
    }

    #[Route(
        '/categories/{uuid}',
        name: 'api_categories_delete',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['DELETE']
    )]
    #[OA\Delete(summary: 'Delete category', description: 'Deletes a category only when no products reference it.')]
    #[OA\Response(response: 204, description: 'Category deleted')]
    #[OA\Response(response: 404, description: 'Category not found')]
    #[OA\Response(response: 409, description: 'Category is in use')]
    public function delete(Uuid $uuid): JsonResponse
    {
        $this->categoryService->delete($uuid);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function toResponse(Category $category): CategoryListItemResponse
    {
        return new CategoryListItemResponse(
            $category->getId(),
            $category->getName(),
            $this->categoryService->usageCount($category)
        );
    }
}
```

- [ ] **Step 2: Run the functional tests (the safety net) to verify they still pass**

Run: `docker compose exec -T php bin/phpunit tests/Functional/Controller/Api/Internal/V1/CategoryControllerTest.php`
Expected: PASS — all tests green, including `testCreateDuplicateNameConflicts` and `testRenameToExistingNameConflicts` (now satisfied via the flush-translation path, still 409 `CATEGORY_NAME_TAKEN`).

- [ ] **Step 3: Verify the controller is clean**

Run:
```bash
grep -n "persist\|flush\|assertNameAvailable\|usageCount(\$" \
  src/Controller/Api/Internal/V1/CategoryController.php
```
Expected: no hits for `persist`, `flush`, `assertNameAvailable`; the only `usageCount` reference is the `$this->categoryService->usageCount(...)` call inside `toResponse` (a delegation, not local logic).

- [ ] **Step 4: Lint, stage, commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint
git add src/Controller/Api/Internal/V1/CategoryController.php
git commit -s -m "refactor(category): delegate CategoryController to CategoryService (#55)"
```

---

## Task 3: LocationService + unit tests

**Files:**
- Create: `tests/Unit/Service/LocationServiceTest.php`
- Create: `src/Service/LocationService.php`

- [ ] **Step 1: Write the failing unit test**

Create `tests/Unit/Service/LocationServiceTest.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service;

use App\Entity\Location;
use App\Exception\Location\LocationInUseException;
use App\Exception\Location\LocationNameTakenException;
use App\Exception\Location\LocationNotFoundException;
use App\Repository\LocationRepository;
use App\Repository\ProductRepository;
use App\Repository\StockEntryRepository;
use App\Request\CreateLocationRequest;
use App\Request\UpdateLocationRequest;
use App\Service\LocationService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class LocationServiceTest extends TestCase
{
    public function testCreateTranslatesUniqueViolationToNameTaken(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush')
            ->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        $service = new LocationService(
            $em,
            $this->createStub(LocationRepository::class),
            $this->createStub(ProductRepository::class),
            $this->createStub(StockEntryRepository::class)
        );

        $this->expectException(LocationNameTakenException::class);
        $service->create(new CreateLocationRequest('Кладовка'));
    }

    public function testUpdateWithSameNameDoesNotFlush(): void
    {
        $existing = new Location();
        $existing->setName('Кладовка');

        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $service = new LocationService(
            $em,
            $repo,
            $this->createStub(ProductRepository::class),
            $this->createStub(StockEntryRepository::class)
        );

        $result = $service->update(Uuid::v7(), new UpdateLocationRequest('Кладовка'));
        static::assertSame('Кладовка', $result->getName());
    }

    public function testUpdateMissingThrowsNotFound(): void
    {
        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn(null);

        $service = new LocationService(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            $this->createStub(ProductRepository::class),
            $this->createStub(StockEntryRepository::class)
        );

        $this->expectException(LocationNotFoundException::class);
        $service->update(Uuid::v7(), new UpdateLocationRequest('Кладовка'));
    }

    public function testDeleteInUseThrowsConflict(): void
    {
        $location = new Location();
        $location->setName('Кладовка');

        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn($location);

        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(1);
        $stock = $this->createStub(StockEntryRepository::class);
        $stock->method('count')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');

        $service = new LocationService($em, $repo, $products, $stock);

        $this->expectException(LocationInUseException::class);
        $service->delete(Uuid::v7());
    }

    public function testDeleteEmptyRemovesAndFlushes(): void
    {
        $location = new Location();
        $location->setName('Кладовка');

        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn($location);

        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(0);
        $stock = $this->createStub(StockEntryRepository::class);
        $stock->method('count')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($location);
        $em->expects($this->once())->method('flush');

        $service = new LocationService($em, $repo, $products, $stock);
        $service->delete(Uuid::v7());
    }

    public function testUsageCountSumsProductsAndStockEntries(): void
    {
        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(2);
        $stock = $this->createStub(StockEntryRepository::class);
        $stock->method('count')->willReturn(5);

        $service = new LocationService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LocationRepository::class),
            $products,
            $stock
        );

        static::assertSame(7, $service->usageCount(new Location()));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec -T php bin/phpunit tests/Unit/Service/LocationServiceTest.php`
Expected: FAIL — `Error: Class "App\Service\LocationService" not found`.

- [ ] **Step 3: Write the minimal implementation**

Create `src/Service/LocationService.php`:

```php
<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\Location;
use App\Exception\Location\LocationInUseException;
use App\Exception\Location\LocationNameTakenException;
use App\Exception\Location\LocationNotFoundException;
use App\Repository\LocationRepository;
use App\Repository\ProductRepository;
use App\Repository\StockEntryRepository;
use App\Request\CreateLocationRequest;
use App\Request\UpdateLocationRequest;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

readonly class LocationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LocationRepository $locationRepository,
        private ProductRepository $productRepository,
        private StockEntryRepository $stockEntryRepository
    ) {
    }

    /** @return Location[] */
    public function list(): array
    {
        return $this->locationRepository->findAllOrderedByName();
    }

    public function create(CreateLocationRequest $request): Location
    {
        $location = new Location();
        $location->setName($request->name);

        $this->em->persist($location);
        $this->flushOrNameTaken($request->name);

        return $location;
    }

    public function update(Uuid $id, UpdateLocationRequest $request): Location
    {
        $location = $this->locationRepository->find($id) ?? throw new LocationNotFoundException($id);

        if ($request->name !== $location->getName()) {
            $location->setName($request->name);
            $this->flushOrNameTaken($request->name);
        }

        return $location;
    }

    public function delete(Uuid $id): void
    {
        $location = $this->locationRepository->find($id) ?? throw new LocationNotFoundException($id);

        $usage = $this->usageCount($location);
        if ($usage > 0) {
            throw new LocationInUseException($usage);
        }

        $this->em->remove($location);
        $this->em->flush();
    }

    public function usageCount(Location $location): int
    {
        return $this->productRepository->count(['defaultLocation' => $location])
            + $this->stockEntryRepository->count(['location' => $location]);
    }

    /**
     * The DB unique constraint is the single authority for name uniqueness;
     * translate its violation (incl. the concurrent-create race) into a clean 409.
     */
    private function flushOrNameTaken(string $name): void
    {
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new LocationNameTakenException($name);
        }
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose exec -T php bin/phpunit tests/Unit/Service/LocationServiceTest.php`
Expected: PASS — all tests OK.

- [ ] **Step 5: Lint, stage, commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint
git add src/Service/LocationService.php tests/Unit/Service/LocationServiceTest.php
git commit -s -m "fix(location): extract LocationService, translate unique violation to 409 (#55)"
```

---

## Task 4: Delegate LocationController to LocationService

**Files:**
- Modify: `src/Controller/Api/Internal/V1/LocationController.php` (full rewrite)
- Safety net: `tests/Functional/Controller/Api/Internal/V1/LocationControllerTest.php` (unchanged, must stay green)

- [ ] **Step 1: Rewrite the controller**

Replace the entire contents of `src/Controller/Api/Internal/V1/LocationController.php` with:

```php
<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Entity\Location;
use App\Request\CreateLocationRequest;
use App\Request\UpdateLocationRequest;
use App\Response\Location\LocationListItemResponse;
use App\Service\LocationService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Locations')]
final class LocationController extends AbstractController
{
    public function __construct(
        private readonly LocationService $locationService
    ) {
    }

    #[Route('/locations', name: 'api_locations_list', methods: ['GET'])]
    #[OA\Get(description: 'Returns all storage locations with usage counts.', summary: 'List locations')]
    #[OA\Response(response: 200, description: 'List of locations', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: LocationListItemResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function list(): JsonResponse
    {
        $data = array_map($this->toResponse(...), $this->locationService->list());

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/locations', name: 'api_locations_create', methods: ['POST'])]
    #[OA\Post(summary: 'Create location', description: 'Creates a storage location.')]
    #[OA\RequestBody(required: true, content: new Model(type: CreateLocationRequest::class))]
    #[OA\Response(response: 201, description: 'Location created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: LocationListItemResponse::class))
    ]))]
    #[OA\Response(response: 409, description: 'Name already exists')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function create(#[MapRequestPayload] CreateLocationRequest $request): JsonResponse
    {
        $location = $this->locationService->create($request);

        return $this->json(['data' => $this->toResponse($location)], Response::HTTP_CREATED);
    }

    #[Route(
        '/locations/{uuid}',
        name: 'api_locations_update',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['PATCH']
    )]
    #[OA\Patch(summary: 'Rename location', description: 'Renames a storage location.')]
    #[OA\RequestBody(required: true, content: new Model(type: UpdateLocationRequest::class))]
    #[OA\Response(response: 200, description: 'Location updated', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: LocationListItemResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Location not found')]
    #[OA\Response(response: 409, description: 'Name already exists')]
    public function update(Uuid $uuid, #[MapRequestPayload] UpdateLocationRequest $request): JsonResponse
    {
        $location = $this->locationService->update($uuid, $request);

        return $this->json(['data' => $this->toResponse($location)]);
    }

    #[Route(
        '/locations/{uuid}',
        name: 'api_locations_delete',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['DELETE']
    )]
    #[OA\Delete(summary: 'Delete location', description: 'Deletes a location only when nothing references it.')]
    #[OA\Response(response: 204, description: 'Location deleted')]
    #[OA\Response(response: 404, description: 'Location not found')]
    #[OA\Response(response: 409, description: 'Location is in use')]
    public function delete(Uuid $uuid): JsonResponse
    {
        $this->locationService->delete($uuid);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function toResponse(Location $location): LocationListItemResponse
    {
        return new LocationListItemResponse(
            $location->getId(),
            $location->getName(),
            $this->locationService->usageCount($location)
        );
    }
}
```

- [ ] **Step 2: Run the functional tests (safety net)**

Run: `docker compose exec -T php bin/phpunit tests/Functional/Controller/Api/Internal/V1/LocationControllerTest.php`
Expected: PASS — all green, including duplicate-name → 409 `LOCATION_NAME_TAKEN` and in-use delete → 409 `LOCATION_IN_USE`.

- [ ] **Step 3: Verify the controller is clean**

Run:
```bash
grep -n "persist\|flush\|assertNameAvailable\|usageCount(\$" \
  src/Controller/Api/Internal/V1/LocationController.php
```
Expected: no hits for `persist`, `flush`, `assertNameAvailable`; only the `$this->locationService->usageCount(...)` delegation in `toResponse`.

- [ ] **Step 4: Lint, stage, commit**

```bash
cd /home/pavel/projects/personal/hestia/backend
make lint
git add src/Controller/Api/Internal/V1/LocationController.php
git commit -s -m "refactor(location): delegate LocationController to LocationService (#55)"
```

---

## Task 5: Full CI-parity gate + acceptance verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full backend lint gate**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: completes with no errors (rector → mago format → mago lint → mago analyze → phpstan). If it reformatted any tracked file, stage just that file (`git add <path>`) and amend the relevant commit or make a `style(...)` commit — never `git add -A`.

- [ ] **Step 2: Run the full backend test suite**

Run: `cd /home/pavel/projects/personal/hestia/backend && make test`
Expected: all tests pass (the 2 new unit test classes + the unchanged Category/Location functional tests + everything else).

- [ ] **Step 3: Run the acceptance verification from the spec**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend
grep -n "persist\|flush\|assertNameAvailable\|usageCount" \
  src/Controller/Api/Internal/V1/CategoryController.php \
  src/Controller/Api/Internal/V1/LocationController.php
grep -rn "UniqueConstraintViolationException" src/Service
```
Expected:
- First grep: the only matches are `usageCount` delegation calls inside the two `toResponse` methods; **no** `persist`, `flush`, or `assertNameAvailable`.
- Second grep: `UniqueConstraintViolationException` appears in both `CategoryService.php` and `LocationService.php`.

- [ ] **Step 4 (optional): Open the PR**

```bash
cd /home/pavel/projects/personal/hestia
git push -u origin fix/category-location-service-extraction
gh pr create --fill --base master
```
In the PR body, note: "Race is fixed by translating the DB `UniqueConstraintViolationException` to 409; true two-transaction concurrency is not simulated in tests — the unit tests assert the translation deterministically via a mocked `flush()`."

---

## Notes for the implementer

- **`#[UniqueEntity]` stays** on `Category` and `Location` entities — do not remove it. It documents the invariant and protects any non-service write path. The service does not invoke the validator for uniqueness; the DB constraint is the authority.
- **Autowiring is automatic** (`config/services.yaml` registers everything under `App\` in `src/`). The new services need no manual service definition; constructor type-hints resolve.
- **Why `createStub(UniqueConstraintViolationException::class)`:** that DBAL exception has an awkward constructor (`Driver\Exception` + `?Query`). PHPUnit's test double subclasses it without invoking the constructor, and the doubled instance is still `instanceof UniqueConstraintViolationException`, so the service's `catch` matches. Do not try to `new` it directly.
- **`ProductRepository`/`StockEntryRepository`/`*Repository::count()`** is the inherited `Doctrine\ORM\EntityRepository::count(array $criteria): int` — mockable and already used by the old controllers with the same criteria keys (`category`, `defaultLocation`, `location`).
```
