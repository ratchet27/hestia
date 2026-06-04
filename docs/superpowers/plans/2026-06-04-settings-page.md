# Settings Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the scaffolded Settings page with a minimal, real one: Locations CRUD, Categories CRUD, and a Telegram status + live test button.

**Architecture:** Extend the existing `Location`/`Category` controllers with create/rename/delete (delete blocked while in use) and add a small `TelegramController` (status from `.env`, a real synchronous test send). Rewrite `SettingsPage` against the regenerated Orval client using a shared `ManagedList` component. No new tables, no settings store.

**Tech Stack:** Symfony 8 / PHP 8.4 (Doctrine, ObjectMapper, Notifier), React 19 / TS / Vite / Bun, TanStack Query, Orval, Vitest + MSW, PHPUnit + Foundry.

**Design:** `docs/superpowers/specs/2026-06-04-settings-page-design.md`

**Conventions (read once):**
- Backend domain errors are thrown as subclasses of `App\Exception\ApiException` carrying an `App\Exception\ApiProblem`; `ApiExceptionListener` renders them as `application/problem+json`. See `src/Exception/Barcode/BarcodeAlreadyExistsException.php` for the 409 shape.
- Controllers under `src/Controller/Api/Internal/V1/`, routes prefixed `/api/internal/v1` (see `config/routes.yaml`). Use `Requirement::UUID_V7` on `{uuid}` params.
- Orval generates a client function per endpoint, named from the route `name`. Use the route names in this plan verbatim so the generated names match the frontend imports.
- Backend gate is `make lint` then `make test` (run from `backend/`, Docker up). Frontend gate is `bun run check` then `bun run test:run` (run from `frontend/`).
- **Staging:** `make lint` rewrites files — `git add` explicit paths, never `git add -A`.

---

## Task 1: Location domain exceptions

**Files:**
- Create: `backend/src/Exception/Location/LocationNotFoundException.php`
- Create: `backend/src/Exception/Location/LocationNameTakenException.php`
- Create: `backend/src/Exception/Location/LocationInUseException.php`

- [ ] **Step 1: Create the three exceptions**

`LocationNotFoundException.php`:
```php
<?php

declare(strict_types = 1);

namespace App\Exception\Location;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class LocationNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Location not found',
            type: 'LOCATION_NOT_FOUND',
            code: Response::HTTP_NOT_FOUND,
            extraData: ['id' => (string) $id]
        ));
    }
}
```

`LocationNameTakenException.php`:
```php
<?php

declare(strict_types = 1);

namespace App\Exception\Location;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class LocationNameTakenException extends ApiException
{
    public function __construct(string $name)
    {
        parent::__construct(new ApiProblem(
            title: 'Location name already exists',
            type: 'LOCATION_NAME_TAKEN',
            code: Response::HTTP_CONFLICT,
            extraData: ['name' => $name]
        ));
    }
}
```

`LocationInUseException.php`:
```php
<?php

declare(strict_types = 1);

namespace App\Exception\Location;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class LocationInUseException extends ApiException
{
    public function __construct(int $usageCount)
    {
        parent::__construct(new ApiProblem(
            title: 'Location is in use and cannot be deleted',
            type: 'LOCATION_IN_USE',
            code: Response::HTTP_CONFLICT,
            extraData: ['usageCount' => $usageCount]
        ));
    }
}
```

- [ ] **Step 2: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Exception/Location
git commit -s -m "feat(locations): add not-found / name-taken / in-use exceptions"
```

---

## Task 2: Category domain exceptions

**Files:**
- Create: `backend/src/Exception/Category/CategoryNotFoundException.php`
- Create: `backend/src/Exception/Category/CategoryNameTakenException.php`
- Create: `backend/src/Exception/Category/CategoryInUseException.php`

> Note: there is an existing `App\Exception\Product\CategoryNotFoundException` (code 400, used in product creation). These new ones live under `App\Exception\Category` and return 404/409 for the management endpoints. Do not reuse the Product one.

- [ ] **Step 1: Create the three exceptions** (mirror Task 1, swapping Location→Category, `LOCATION_`→`CATEGORY_`, and titles "Category ...")

`CategoryNotFoundException.php`:
```php
<?php

declare(strict_types = 1);

namespace App\Exception\Category;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class CategoryNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Category not found',
            type: 'CATEGORY_NOT_FOUND',
            code: Response::HTTP_NOT_FOUND,
            extraData: ['id' => (string) $id]
        ));
    }
}
```

`CategoryNameTakenException.php`:
```php
<?php

declare(strict_types = 1);

namespace App\Exception\Category;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class CategoryNameTakenException extends ApiException
{
    public function __construct(string $name)
    {
        parent::__construct(new ApiProblem(
            title: 'Category name already exists',
            type: 'CATEGORY_NAME_TAKEN',
            code: Response::HTTP_CONFLICT,
            extraData: ['name' => $name]
        ));
    }
}
```

`CategoryInUseException.php`:
```php
<?php

declare(strict_types = 1);

namespace App\Exception\Category;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;

final class CategoryInUseException extends ApiException
{
    public function __construct(int $usageCount)
    {
        parent::__construct(new ApiProblem(
            title: 'Category is in use and cannot be deleted',
            type: 'CATEGORY_IN_USE',
            code: Response::HTTP_CONFLICT,
            extraData: ['usageCount' => $usageCount]
        ));
    }
}
```

- [ ] **Step 2: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Exception/Category
git commit -s -m "feat(categories): add not-found / name-taken / in-use exceptions"
```

---

## Task 3: Request DTOs for create/rename

**Files:**
- Create: `backend/src/Request/CreateLocationRequest.php`
- Create: `backend/src/Request/UpdateLocationRequest.php`
- Create: `backend/src/Request/CreateCategoryRequest.php`
- Create: `backend/src/Request/UpdateCategoryRequest.php`

- [ ] **Step 1: Create the four DTOs**

`CreateLocationRequest.php` (the other three are identical except class name):
```php
<?php

declare(strict_types = 1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateLocationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name
    ) {
    }
}
```

`UpdateLocationRequest.php` — same body, class `UpdateLocationRequest`.
`CreateCategoryRequest.php` — same body, class `CreateCategoryRequest`.
`UpdateCategoryRequest.php` — same body, class `UpdateCategoryRequest`.

- [ ] **Step 2: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Request/CreateLocationRequest.php backend/src/Request/UpdateLocationRequest.php \
        backend/src/Request/CreateCategoryRequest.php backend/src/Request/UpdateCategoryRequest.php
git commit -s -m "feat(settings): add create/rename request DTOs for locations and categories"
```

---

## Task 4: Add `usageCount` to the list responses

**Files:**
- Modify: `backend/src/Response/Location/LocationResponse.php`
- Modify: `backend/src/Response/Category/CategoryResponse.php`

The field is added with a default of `0` so existing ObjectMapper-based construction (`#[Map(source: ...)]`) keeps working — the mapper fills `id`/`name` and leaves `usageCount` at its default. The list endpoints (Tasks 5/6) construct these manually with the real count.

- [ ] **Step 1: Add the field**

`LocationResponse.php`:
```php
<?php

declare(strict_types = 1);

namespace App\Response\Location;

use App\Entity\Location;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;

#[Map(source: Location::class)]
final readonly class LocationResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public int $usageCount = 0
    ) {
    }
}
```

`CategoryResponse.php` — identical change (`use App\Entity\Category;`, `#[Map(source: Category::class)]`, same three params).

- [ ] **Step 2: Verify backend still boots**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/console cache:clear`
Expected: clears without error.

- [ ] **Step 3: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Response/Location/LocationResponse.php backend/src/Response/Category/CategoryResponse.php
git commit -s -m "feat(settings): add usageCount to location/category responses"
```

---

## Task 5: LocationController CRUD

**Files:**
- Modify: `backend/src/Controller/Api/Internal/V1/LocationController.php`
- Create: `backend/tests/Functional/Controller/Api/Internal/V1/LocationControllerTest.php`

`usageCount` = (# products whose `defaultLocation` is it) + (# stock entries in it), via Doctrine's built-in `EntityRepository::count(criteria)`.

- [ ] **Step 1: Write the failing functional test**

`LocationControllerTest.php`:
```php
<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Location;
use App\Factory\LocationFactory;
use App\Factory\ProductFactory;
use App\Factory\UserFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LocationControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loginAs(UserFactory::createOne());
    }

    public function testListIncludesUsageCount(): void
    {
        $location = LocationFactory::createOne(['name' => 'Гараж']);
        ProductFactory::createOne(['defaultLocation' => $location]);

        $response = $this->apiGet('/locations');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        $garage = array_values(array_filter($data['data'], fn($l) => $l['name'] === 'Гараж'))[0];
        static::assertSame(1, $garage['usageCount']);
    }

    public function testCreateLocation(): void
    {
        $response = $this->apiPost('/locations', ['name' => 'Балкон']);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('Балкон', $data['data']['name']);
        static::assertSame(0, $data['data']['usageCount']);
        $this->assertDatabaseHas(Location::class, ['name' => 'Балкон']);
    }

    public function testCreateDuplicateNameConflicts(): void
    {
        LocationFactory::createOne(['name' => 'Балкон']);

        $response = $this->apiPost('/locations', ['name' => 'Балкон']);
        $data = static::assertErrorResponse($response, Response::HTTP_CONFLICT);

        static::assertSame('LOCATION_NAME_TAKEN', $data['type']);
    }

    public function testCreateBlankNameIsUnprocessable(): void
    {
        $response = $this->apiPost('/locations', ['name' => '']);
        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRenameLocation(): void
    {
        $location = LocationFactory::createOne(['name' => 'Балкон']);

        $response = $this->apiPatch('/locations/' . $location->getId(), ['name' => 'Лоджия']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('Лоджия', $data['data']['name']);
        $this->assertDatabaseHas(Location::class, ['name' => 'Лоджия']);
    }

    public function testRenameMissingLocationIsNotFound(): void
    {
        $response = $this->apiPatch('/locations/' . Uuid::v7(), ['name' => 'Лоджия']);
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);
        static::assertSame('LOCATION_NOT_FOUND', $data['type']);
    }

    public function testDeleteEmptyLocation(): void
    {
        $location = LocationFactory::createOne(['name' => 'Балкон']);

        $response = $this->apiDelete('/locations/' . $location->getId());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $this->assertDatabaseMissing(Location::class, ['name' => 'Балкон']);
    }

    public function testDeleteInUseLocationConflicts(): void
    {
        $location = LocationFactory::createOne(['name' => 'Гараж']);
        ProductFactory::createOne(['defaultLocation' => $location]);

        $response = $this->apiDelete('/locations/' . $location->getId());
        $data = static::assertErrorResponse($response, Response::HTTP_CONFLICT);

        static::assertSame('LOCATION_IN_USE', $data['type']);
        static::assertSame(1, $data['usageCount']);
        $this->assertDatabaseHas(Location::class, ['name' => 'Гараж']);
    }
}
```

- [ ] **Step 2: Run the test, expect failure**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Functional/Controller/Api/Internal/V1/LocationControllerTest.php`
Expected: FAIL (routes for POST/PATCH/DELETE not defined → 404/405; `usageCount` key missing).

- [ ] **Step 3: Implement the controller**

Replace `LocationController.php` with:
```php
<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Entity\Location;
use App\Entity\Product;
use App\Entity\StockEntry;
use App\Exception\Location\LocationInUseException;
use App\Exception\Location\LocationNameTakenException;
use App\Exception\Location\LocationNotFoundException;
use App\Repository\LocationRepository;
use App\Request\CreateLocationRequest;
use App\Request\UpdateLocationRequest;
use App\Response\Location\LocationResponse;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly LocationRepository $locationRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    #[Route('/locations', name: 'api_locations_list', methods: ['GET'])]
    #[OA\Get(description: 'Returns all storage locations with usage counts.', summary: 'List locations')]
    #[OA\Response(response: 200, description: 'List of locations', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: LocationResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function list(): JsonResponse
    {
        $locations = $this->locationRepository->findAllOrderedByName();
        $data = array_map(fn(Location $l) => $this->toResponse($l), $locations);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/locations', name: 'api_locations_create', methods: ['POST'])]
    #[OA\Post(summary: 'Create location', description: 'Creates a storage location.')]
    #[OA\RequestBody(required: true, content: new Model(type: CreateLocationRequest::class))]
    #[OA\Response(response: 201, description: 'Location created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: LocationResponse::class))
    ]))]
    #[OA\Response(response: 409, description: 'Name already exists')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function create(#[MapRequestPayload] CreateLocationRequest $request): JsonResponse
    {
        $this->assertNameAvailable($request->name);

        $location = new Location();
        $location->setName($request->name);
        $this->em->persist($location);
        $this->em->flush();

        return $this->json(['data' => $this->toResponse($location)], Response::HTTP_CREATED);
    }

    #[Route('/locations/{uuid}', name: 'api_locations_update', requirements: ['uuid' => Requirement::UUID_V7], methods: ['PATCH'])]
    #[OA\Patch(summary: 'Rename location', description: 'Renames a storage location.')]
    #[OA\RequestBody(required: true, content: new Model(type: UpdateLocationRequest::class))]
    #[OA\Response(response: 200, description: 'Location updated', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: LocationResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Location not found')]
    #[OA\Response(response: 409, description: 'Name already exists')]
    public function update(Uuid $uuid, #[MapRequestPayload] UpdateLocationRequest $request): JsonResponse
    {
        $location = $this->locationRepository->find($uuid) ?? throw new LocationNotFoundException($uuid);

        if ($request->name !== $location->getName()) {
            $this->assertNameAvailable($request->name);
            $location->setName($request->name);
            $this->em->flush();
        }

        return $this->json(['data' => $this->toResponse($location)]);
    }

    #[Route('/locations/{uuid}', name: 'api_locations_delete', requirements: ['uuid' => Requirement::UUID_V7], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Delete location', description: 'Deletes a location only when nothing references it.')]
    #[OA\Response(response: 204, description: 'Location deleted')]
    #[OA\Response(response: 404, description: 'Location not found')]
    #[OA\Response(response: 409, description: 'Location is in use')]
    public function delete(Uuid $uuid): JsonResponse
    {
        $location = $this->locationRepository->find($uuid) ?? throw new LocationNotFoundException($uuid);

        $usage = $this->usageCount($location);
        if ($usage > 0) {
            throw new LocationInUseException($usage);
        }

        $this->em->remove($location);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function toResponse(Location $location): LocationResponse
    {
        return new LocationResponse($location->getId(), $location->getName(), $this->usageCount($location));
    }

    private function usageCount(Location $location): int
    {
        return $this->em->getRepository(Product::class)->count(['defaultLocation' => $location])
            + $this->em->getRepository(StockEntry::class)->count(['location' => $location]);
    }

    private function assertNameAvailable(string $name): void
    {
        if ($this->locationRepository->findOneBy(['name' => $name]) !== null) {
            throw new LocationNameTakenException($name);
        }
    }
}
```

> If `Location` has no `setName()` setter, add one (mirror `getName()`); check `src/Entity/Location.php` first.

- [ ] **Step 4: Run the test, expect pass**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Functional/Controller/Api/Internal/V1/LocationControllerTest.php`
Expected: PASS (all 8 tests).

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Controller/Api/Internal/V1/LocationController.php \
        backend/tests/Functional/Controller/Api/Internal/V1/LocationControllerTest.php
git commit -s -m "feat(locations): add create/rename/delete with in-use guard"
```

---

## Task 6: CategoryController CRUD

**Files:**
- Modify: `backend/src/Controller/Api/Internal/V1/CategoryController.php`
- Create: `backend/tests/Functional/Controller/Api/Internal/V1/CategoryControllerTest.php`

`usageCount` = # products in the category.

- [ ] **Step 1: Write the failing functional test**

`CategoryControllerTest.php` — same structure as Task 5's test with these substitutions: `Location`→`Category`, `LocationFactory`→`CategoryFactory`, route `/locations`→`/categories`, error types `LOCATION_*`→`CATEGORY_*`, and the in-use product uses `['category' => $category]` instead of `['defaultLocation' => $location]`. Full code:

```php
<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Category;
use App\Factory\CategoryFactory;
use App\Factory\ProductFactory;
use App\Factory\UserFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CategoryControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loginAs(UserFactory::createOne());
    }

    public function testListIncludesUsageCount(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Снеки']);
        ProductFactory::createOne(['category' => $category]);

        $response = $this->apiGet('/categories');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        $snacks = array_values(array_filter($data['data'], fn($c) => $c['name'] === 'Снеки'))[0];
        static::assertSame(1, $snacks['usageCount']);
    }

    public function testCreateCategory(): void
    {
        $response = $this->apiPost('/categories', ['name' => 'Снеки']);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('Снеки', $data['data']['name']);
        static::assertSame(0, $data['data']['usageCount']);
        $this->assertDatabaseHas(Category::class, ['name' => 'Снеки']);
    }

    public function testCreateDuplicateNameConflicts(): void
    {
        CategoryFactory::createOne(['name' => 'Снеки']);

        $response = $this->apiPost('/categories', ['name' => 'Снеки']);
        $data = static::assertErrorResponse($response, Response::HTTP_CONFLICT);
        static::assertSame('CATEGORY_NAME_TAKEN', $data['type']);
    }

    public function testCreateBlankNameIsUnprocessable(): void
    {
        $response = $this->apiPost('/categories', ['name' => '']);
        static::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRenameCategory(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Снеки']);

        $response = $this->apiPatch('/categories/' . $category->getId(), ['name' => 'Закуски']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);
        static::assertSame('Закуски', $data['data']['name']);
    }

    public function testRenameMissingCategoryIsNotFound(): void
    {
        $response = $this->apiPatch('/categories/' . Uuid::v7(), ['name' => 'Закуски']);
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);
        static::assertSame('CATEGORY_NOT_FOUND', $data['type']);
    }

    public function testDeleteEmptyCategory(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Снеки']);

        $response = $this->apiDelete('/categories/' . $category->getId());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $this->assertDatabaseMissing(Category::class, ['name' => 'Снеки']);
    }

    public function testDeleteInUseCategoryConflicts(): void
    {
        $category = CategoryFactory::createOne(['name' => 'Снеки']);
        ProductFactory::createOne(['category' => $category]);

        $response = $this->apiDelete('/categories/' . $category->getId());
        $data = static::assertErrorResponse($response, Response::HTTP_CONFLICT);
        static::assertSame('CATEGORY_IN_USE', $data['type']);
        static::assertSame(1, $data['usageCount']);
    }
}
```

- [ ] **Step 2: Run the test, expect failure**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Functional/Controller/Api/Internal/V1/CategoryControllerTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement the controller**

Replace `CategoryController.php` (mirror of Task 5's LocationController, for `Category`; usage = product count only):
```php
<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Entity\Category;
use App\Entity\Product;
use App\Exception\Category\CategoryInUseException;
use App\Exception\Category\CategoryNameTakenException;
use App\Exception\Category\CategoryNotFoundException;
use App\Repository\CategoryRepository;
use App\Request\CreateCategoryRequest;
use App\Request\UpdateCategoryRequest;
use App\Response\Category\CategoryResponse;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    #[Route('/categories', name: 'api_categories_list', methods: ['GET'])]
    #[OA\Get(description: 'Returns all product categories with usage counts.', summary: 'List categories')]
    #[OA\Response(response: 200, description: 'List of categories', content: new OA\JsonContent(properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(ref: new Model(type: CategoryResponse::class))
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'total', type: 'integer')],
            type: 'object'
        )
    ]))]
    public function list(): JsonResponse
    {
        $categories = $this->categoryRepository->findAllOrderedByName();
        $data = array_map(fn(Category $c) => $this->toResponse($c), $categories);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route('/categories', name: 'api_categories_create', methods: ['POST'])]
    #[OA\Post(summary: 'Create category', description: 'Creates a product category.')]
    #[OA\RequestBody(required: true, content: new Model(type: CreateCategoryRequest::class))]
    #[OA\Response(response: 201, description: 'Category created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: CategoryResponse::class))
    ]))]
    #[OA\Response(response: 409, description: 'Name already exists')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function create(#[MapRequestPayload] CreateCategoryRequest $request): JsonResponse
    {
        $this->assertNameAvailable($request->name);

        $category = new Category();
        $category->setName($request->name);
        $this->em->persist($category);
        $this->em->flush();

        return $this->json(['data' => $this->toResponse($category)], Response::HTTP_CREATED);
    }

    #[Route('/categories/{uuid}', name: 'api_categories_update', requirements: ['uuid' => Requirement::UUID_V7], methods: ['PATCH'])]
    #[OA\Patch(summary: 'Rename category', description: 'Renames a product category.')]
    #[OA\RequestBody(required: true, content: new Model(type: UpdateCategoryRequest::class))]
    #[OA\Response(response: 200, description: 'Category updated', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: CategoryResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Category not found')]
    #[OA\Response(response: 409, description: 'Name already exists')]
    public function update(Uuid $uuid, #[MapRequestPayload] UpdateCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryRepository->find($uuid) ?? throw new CategoryNotFoundException($uuid);

        if ($request->name !== $category->getName()) {
            $this->assertNameAvailable($request->name);
            $category->setName($request->name);
            $this->em->flush();
        }

        return $this->json(['data' => $this->toResponse($category)]);
    }

    #[Route('/categories/{uuid}', name: 'api_categories_delete', requirements: ['uuid' => Requirement::UUID_V7], methods: ['DELETE'])]
    #[OA\Delete(summary: 'Delete category', description: 'Deletes a category only when no products reference it.')]
    #[OA\Response(response: 204, description: 'Category deleted')]
    #[OA\Response(response: 404, description: 'Category not found')]
    #[OA\Response(response: 409, description: 'Category is in use')]
    public function delete(Uuid $uuid): JsonResponse
    {
        $category = $this->categoryRepository->find($uuid) ?? throw new CategoryNotFoundException($uuid);

        $usage = $this->usageCount($category);
        if ($usage > 0) {
            throw new CategoryInUseException($usage);
        }

        $this->em->remove($category);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function toResponse(Category $category): CategoryResponse
    {
        return new CategoryResponse($category->getId(), $category->getName(), $this->usageCount($category));
    }

    private function usageCount(Category $category): int
    {
        return $this->em->getRepository(Product::class)->count(['category' => $category]);
    }

    private function assertNameAvailable(string $name): void
    {
        if ($this->categoryRepository->findOneBy(['name' => $name]) !== null) {
            throw new CategoryNameTakenException($name);
        }
    }
}
```

> If `Category` has no `setName()`, add one (check `src/Entity/Category.php`).

- [ ] **Step 4: Run the test, expect pass**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Functional/Controller/Api/Internal/V1/CategoryControllerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Controller/Api/Internal/V1/CategoryController.php \
        backend/tests/Functional/Controller/Api/Internal/V1/CategoryControllerTest.php
git commit -s -m "feat(categories): add create/rename/delete with in-use guard"
```

---

## Task 7: TelegramController (status + test)

**Files:**
- Create: `backend/src/Response/Telegram/TelegramStatusResponse.php`
- Create: `backend/src/Controller/Api/Internal/V1/TelegramController.php`
- Create: `backend/tests/Functional/Controller/Api/Internal/V1/TelegramControllerTest.php`

`/telegram/test` performs a **real synchronous send** via the existing `TelegramSender` (no new send-path test — see spec §6). `/telegram/status` derives `configured` from `TELEGRAM_DSN` (the unset default in `.env` is `telegram://TOKEN@default?channel=CHATID`).

- [ ] **Step 1: Create the status response DTO**

`TelegramStatusResponse.php`:
```php
<?php

declare(strict_types = 1);

namespace App\Response\Telegram;

final readonly class TelegramStatusResponse
{
    public function __construct(
        public bool $configured,
        public string $dailySummaryTime
    ) {
    }
}
```

- [ ] **Step 2: Write the failing functional test (status only)**

`TelegramControllerTest.php`:
```php
<?php

declare(strict_types = 1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Factory\UserFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TelegramControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->loginAs(UserFactory::createOne());
    }

    public function testStatusReturnsShape(): void
    {
        $response = $this->apiGet('/telegram/status');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertArrayHasKey('configured', $data['data']);
        static::assertIsBool($data['data']['configured']);
        static::assertArrayHasKey('dailySummaryTime', $data['data']);
        // .env test default is the placeholder DSN → not configured
        static::assertFalse($data['data']['configured']);
    }
}
```

> Unauthenticated access (401) to `/api/internal/v1/**` is already covered by the existing protected-routes security test — don't re-test it here (and don't call `static::createClient()` twice in one test; Symfony forbids it).

- [ ] **Step 3: Run the test, expect failure**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Functional/Controller/Api/Internal/V1/TelegramControllerTest.php`
Expected: FAIL (route not found → 404).

- [ ] **Step 4: Implement the controller**

`TelegramController.php`:
```php
<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Response\Telegram\TelegramStatusResponse;
use App\Service\Telegram\TelegramSender;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Telegram')]
final class TelegramController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(TELEGRAM_DSN)%')]
        private readonly string $telegramDsn,
        #[Autowire('%env(TELEGRAM_DAILY_SUMMARY_TIME)%')]
        private readonly string $dailySummaryTime,
        private readonly TelegramSender $telegramSender
    ) {
    }

    #[Route('/telegram/status', name: 'api_telegram_status', methods: ['GET'])]
    #[OA\Get(summary: 'Telegram status', description: 'Whether the bot is configured and the daily summary time. No secrets returned.')]
    #[OA\Response(response: 200, description: 'Status')]
    public function status(): JsonResponse
    {
        return $this->json([
            'data' => new TelegramStatusResponse($this->isConfigured(), $this->dailySummaryTime)
        ]);
    }

    #[Route('/telegram/test', name: 'api_telegram_test', methods: ['POST'])]
    #[OA\Post(summary: 'Send Telegram test', description: 'Sends a real test message synchronously to the configured chat.')]
    #[OA\Response(response: 200, description: 'Delivery result')]
    public function test(): JsonResponse
    {
        if (!$this->isConfigured()) {
            return $this->json(['data' => ['ok' => false, 'error' => 'not_configured']]);
        }

        try {
            $this->telegramSender->send('🔔 Hestia — тестовое сообщение / test message');

            return $this->json(['data' => ['ok' => true]]);
        } catch (\Throwable $throwable) {
            return $this->json(['data' => ['ok' => false, 'error' => $throwable->getMessage()]]);
        }
    }

    private function isConfigured(): bool
    {
        return $this->telegramDsn !== '' && !str_starts_with($this->telegramDsn, 'telegram://TOKEN@');
    }
}
```

- [ ] **Step 5: Run the test, expect pass**

Run: `cd /home/pavel/projects/personal/hestia/backend && docker compose exec -T php bin/phpunit tests/Functional/Controller/Api/Internal/V1/TelegramControllerTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src/Response/Telegram backend/src/Controller/Api/Internal/V1/TelegramController.php \
        backend/tests/Functional/Controller/Api/Internal/V1/TelegramControllerTest.php
git commit -s -m "feat(telegram): add status + real synchronous test-send endpoints"
```

---

## Task 8: Backend gate

- [ ] **Step 1: Run the full backend gate**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: rector → mago format → mago lint → mago analyze → phpstan all clean. `make lint` may rewrite files (formatting) — re-stage explicitly if so.

- [ ] **Step 2: Run the full test suite**

Run: `cd /home/pavel/projects/personal/hestia/backend && make test`
Expected: full suite green (existing + new Location/Category/Telegram tests).

- [ ] **Step 3: Commit any lint fixups**

```bash
cd /home/pavel/projects/personal/hestia
git add backend/src backend/tests
git commit -s -m "style(settings): apply make lint fixups" || echo "nothing to commit"
```

---

## Task 9: Regenerate the API client

**Files:**
- Generated (do not hand-edit): `frontend/src/api/generated/**`

- [ ] **Step 1: Ensure backend is up, then regenerate**

Run:
```bash
cd /home/pavel/projects/personal/hestia/backend && docker compose up -d
cd /home/pavel/projects/personal/hestia/frontend && NODE_TLS_REJECT_UNAUTHORIZED=0 bun run generate-api
```
Expected: regenerates `src/api/generated/...`. New functions appear:
- `categories/categories.ts`: `postApiCategoriesCreate`, `patchApiCategoriesUpdate`, `deleteApiCategoriesDelete`
- `locations/locations.ts`: `postApiLocationsCreate`, `patchApiLocationsUpdate`, `deleteApiLocationsDelete`
- `telegram/telegram.ts`: `getApiTelegramStatus`, `postApiTelegramTest`
- `models/`: `createLocationRequest.ts`, `updateLocationRequest.ts`, `createCategoryRequest.ts`, `updateCategoryRequest.ts`, `telegramStatusResponse.ts`; `locationResponse.ts` / `categoryResponse.ts` now include `usageCount`.

> Generated function names are derived from route `name`. If a name differs from the above, use the actual generated export in Task 10 — verify by reading the generated file.

- [ ] **Step 2: Verify the frontend still type-checks**

Run: `cd /home/pavel/projects/personal/hestia/frontend && bunx tsc --noEmit`
Expected: no new errors from generated code.

- [ ] **Step 3: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add frontend/src/api/generated
git commit -s -m "chore(api): regenerate client for settings endpoints"
```

---

## Task 10: Frontend query hooks

**Files:**
- Modify: `frontend/src/api/queries/locations.ts`
- Modify: `frontend/src/api/queries/categories.ts`
- Create: `frontend/src/api/queries/telegram.ts`
- Modify: `frontend/src/api/queries/keys.ts`

- [ ] **Step 1: Add the telegram query key**

In `keys.ts`, add inside the `queryKeys` object (after `chores`):
```ts
  // Telegram
  telegram: {
    status: ["telegram", "status"] as const,
  },
```

- [ ] **Step 2: Extend `locations.ts` with mutations**

Replace `locations.ts` with:
```ts
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  deleteApiLocationsDelete,
  getApiLocationsList,
  patchApiLocationsUpdate,
  postApiLocationsCreate,
} from "../generated/locations/locations";
import { queryKeys } from "./keys";

export function useLocations() {
  return useQuery({
    queryKey: queryKeys.locations.all,
    queryFn: async () => {
      const response = await getApiLocationsList();
      return response.data.data ?? [];
    },
    staleTime: 10 * 60 * 1000,
  });
}

export function useCreateLocation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (name: string) => postApiLocationsCreate({ name }),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: queryKeys.locations.all }),
  });
}

export function useRenameLocation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, name }: { id: string; name: string }) =>
      patchApiLocationsUpdate(id, { name }),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: queryKeys.locations.all }),
  });
}

export function useDeleteLocation() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => deleteApiLocationsDelete(id),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: queryKeys.locations.all }),
  });
}
```

- [ ] **Step 3: Extend `categories.ts` with mutations**

Replace `categories.ts` with the same shape as Step 2, swapping `locations`→`categories`, `Location`→`Category`, and the generated imports (`postApiCategoriesCreate`, `patchApiCategoriesUpdate`, `deleteApiCategoriesDelete`, `getApiCategoriesList`) and `queryKeys.categories.all`.

- [ ] **Step 4: Create `telegram.ts`**

```ts
import { useMutation, useQuery } from "@tanstack/react-query";
import {
  getApiTelegramStatus,
  postApiTelegramTest,
} from "../generated/telegram/telegram";
import { queryKeys } from "./keys";

export function useTelegramStatus() {
  return useQuery({
    queryKey: queryKeys.telegram.status,
    queryFn: async () => {
      const response = await getApiTelegramStatus();
      return response.data.data;
    },
    staleTime: 5 * 60 * 1000,
  });
}

export function useSendTelegramTest() {
  return useMutation({
    mutationFn: async () => {
      const response = await postApiTelegramTest();
      return response.data.data;
    },
  });
}
```

> The exact unwrap depth (`.data.data`) follows the `apiFetch` envelope; if `tsc` complains, read a sibling hook (e.g. `shoppingList.ts`) for the precise generated shape and match it.

- [ ] **Step 5: Verify type-check**

Run: `cd /home/pavel/projects/personal/hestia/frontend && bunx tsc --noEmit`
Expected: clean.

- [ ] **Step 6: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add frontend/src/api/queries
git commit -s -m "feat(settings): add query hooks for location/category CRUD and telegram"
```

---

## Task 11: `ManagedList` component

**Files:**
- Create: `frontend/src/features/settings/ManagedList.tsx`
- Create: `frontend/src/features/settings/ManagedList.test.tsx`

A presentational component: list of `{ id, name, usageCount }`, inline add, inline rename, delete disabled when `usageCount > 0`. No data fetching inside — parents pass handlers.

- [ ] **Step 1: Write the failing test**

`ManagedList.test.tsx`:
```tsx
import { describe, expect, it, vi } from "vitest";
import { render, screen, userEvent } from "@/test/utils";
import { ManagedList } from "./ManagedList";

const items = [
  { id: "a", name: "Холодильник", usageCount: 0 },
  { id: "b", name: "Кладовая", usageCount: 4 },
];

function setup(overrides = {}) {
  const props = {
    title: "Места хранения",
    items,
    onAdd: vi.fn().mockResolvedValue(undefined),
    onRename: vi.fn().mockResolvedValue(undefined),
    onDelete: vi.fn().mockResolvedValue(undefined),
    ...overrides,
  };
  render(<ManagedList {...props} />);
  return props;
}

describe("ManagedList", () => {
  it("disables delete for in-use items and enables it for empty ones", () => {
    setup();
    const emptyDelete = screen.getByRole("button", { name: /удалить «Холодильник»/i });
    const usedDelete = screen.getByRole("button", { name: /удалить «Кладовая»/i });
    expect(emptyDelete).toBeEnabled();
    expect(usedDelete).toBeDisabled();
  });

  it("shows the usage count for in-use items", () => {
    setup();
    expect(screen.getByText(/используется: 4/i)).toBeInTheDocument();
  });

  it("calls onAdd with the typed name", async () => {
    const props = setup();
    const user = userEvent.setup();
    await user.type(screen.getByPlaceholderText(/добавить/i), "Балкон");
    await user.click(screen.getByRole("button", { name: /^добавить$/i }));
    expect(props.onAdd).toHaveBeenCalledWith("Балкон");
  });

  it("calls onDelete for an empty item", async () => {
    const props = setup();
    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: /удалить «Холодильник»/i }));
    expect(props.onDelete).toHaveBeenCalledWith("a");
  });
});
```

- [ ] **Step 2: Run, expect failure**

Run: `cd /home/pavel/projects/personal/hestia/frontend && bun run test:run src/features/settings/ManagedList.test.tsx`
Expected: FAIL (module not found).

- [ ] **Step 3: Implement `ManagedList.tsx`**

```tsx
import { useState } from "react";

export interface ManagedItem {
  id: string;
  name: string;
  // Optional so a generated LocationResponse/CategoryResponse (where Orval may
  // mark usageCount optional) is assignable without mapping. Treated as 0 below.
  usageCount?: number;
}

interface ManagedListProps {
  title: string;
  items: ManagedItem[];
  onAdd: (name: string) => Promise<unknown>;
  onRename: (id: string, name: string) => Promise<unknown>;
  onDelete: (id: string) => Promise<unknown>;
}

export function ManagedList({
  title,
  items,
  onAdd,
  onRename,
  onDelete,
}: ManagedListProps): React.ReactElement {
  const [newName, setNewName] = useState("");
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editName, setEditName] = useState("");

  const submitAdd = async () => {
    const name = newName.trim();
    if (!name) return;
    await onAdd(name);
    setNewName("");
  };

  const submitRename = async (id: string) => {
    const name = editName.trim();
    if (name) await onRename(id, name);
    setEditingId(null);
  };

  return (
    <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
      <h3 className="font-semibold text-stone-800 mb-4">{title}</h3>
      <div className="space-y-2">
        {items.map((item) => (
          <div
            key={item.id}
            className="flex items-center justify-between py-2 border-b border-stone-100 last:border-0"
          >
            {editingId === item.id ? (
              <input
                autoFocus
                value={editName}
                onChange={(e) => setEditName(e.target.value)}
                onBlur={() => submitRename(item.id)}
                onKeyDown={(e) => e.key === "Enter" && submitRename(item.id)}
                className="px-2 py-1 border border-stone-300 rounded"
              />
            ) : (
              <button
                type="button"
                onClick={() => {
                  setEditingId(item.id);
                  setEditName(item.name);
                }}
                className="text-stone-800 hover:underline"
              >
                {item.name}
              </button>
            )}
            <div className="flex items-center gap-3">
              {(item.usageCount ?? 0) > 0 && (
                <span className="text-xs text-stone-400">
                  используется: {item.usageCount}
                </span>
              )}
              <button
                type="button"
                aria-label={`Удалить «${item.name}»`}
                disabled={(item.usageCount ?? 0) > 0}
                onClick={() => onDelete(item.id)}
                className="text-sm text-stone-500 hover:text-red-500 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:text-stone-500"
              >
                Удалить
              </button>
            </div>
          </div>
        ))}
      </div>
      <div className="mt-4 flex gap-2">
        <input
          value={newName}
          onChange={(e) => setNewName(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && submitAdd()}
          placeholder="Добавить…"
          className="flex-1 px-3 py-2 border border-stone-300 rounded-lg text-sm"
        />
        <button
          type="button"
          onClick={submitAdd}
          className="px-4 py-2 text-sm text-amber-600 hover:underline"
        >
          Добавить
        </button>
      </div>
    </div>
  );
}
```

> The test asserts Russian literals for component isolation. In Task 13 the page passes i18n strings for `title`; the in-component labels (Удалить / Добавить / используется) stay literal here to keep this unit self-contained — acceptable for a settings-only component. If you prefer full i18n, thread `t` in and update the test's matchers to the resolved RU strings.

- [ ] **Step 4: Run, expect pass**

Run: `cd /home/pavel/projects/personal/hestia/frontend && bun run test:run src/features/settings/ManagedList.test.tsx`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add frontend/src/features/settings/ManagedList.tsx frontend/src/features/settings/ManagedList.test.tsx
git commit -s -m "feat(settings): add ManagedList component for locations/categories"
```

---

## Task 12: i18n keys for Settings

**Files:**
- Modify: `frontend/src/i18n/locales/ru.json`
- Modify: `frontend/src/i18n/locales/en.json`

There is no `settings` block yet. Add one to both files (top-level key, sibling of `nav`, `products`, etc.).

- [ ] **Step 1: Add the `settings` block to `ru.json`**

```json
  "settings": {
    "title": "Настройки",
    "subtitle": "Конфигурация системы",
    "language": "Язык / Language",
    "locations": "Места хранения",
    "categories": "Категории",
    "telegram": "Telegram",
    "telegramConfigured": "Настроено",
    "telegramNotConfigured": "Не настроено",
    "telegramDailyTime": "Ежедневный отчёт",
    "telegramSendTest": "Отправить тест",
    "telegramTestOk": "Тестовое сообщение отправлено",
    "telegramTestFailed": "Не удалось отправить тестовое сообщение"
  }
```

- [ ] **Step 2: Add the matching `settings` block to `en.json`**

```json
  "settings": {
    "title": "Settings",
    "subtitle": "System configuration",
    "language": "Язык / Language",
    "locations": "Storage locations",
    "categories": "Categories",
    "telegram": "Telegram",
    "telegramConfigured": "Configured",
    "telegramNotConfigured": "Not configured",
    "telegramDailyTime": "Daily summary",
    "telegramSendTest": "Send test",
    "telegramTestOk": "Test message sent",
    "telegramTestFailed": "Failed to send test message"
  }
```

> Insert as a valid JSON member (add the trailing comma on the preceding block). Verify with `bunx tsc --noEmit` or by importing — JSON parse errors will surface in the test run.

- [ ] **Step 3: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add frontend/src/i18n/locales/ru.json frontend/src/i18n/locales/en.json
git commit -s -m "feat(i18n): add Settings translation keys (ru/en)"
```

---

## Task 13: Rewrite `SettingsPage`

**Files:**
- Modify: `frontend/src/features/settings/SettingsPage.tsx`
- Create: `frontend/src/features/settings/SettingsPage.test.tsx`

- [ ] **Step 1: Write the failing test (Telegram section)**

`SettingsPage.test.tsx`:
```tsx
import { HttpResponse, http } from "msw";
import { describe, expect, it } from "vitest";
import { server } from "@/test/mocks/server";
import { render, screen, userEvent, waitFor } from "@/test/utils";
import { SettingsPage } from "./SettingsPage";

function mockBaseEndpoints() {
  server.use(
    http.get("*/api/internal/v1/locations", () =>
      HttpResponse.json({ data: [], meta: { total: 0 } }),
    ),
    http.get("*/api/internal/v1/categories", () =>
      HttpResponse.json({ data: [], meta: { total: 0 } }),
    ),
    http.get("*/api/internal/v1/telegram/status", () =>
      HttpResponse.json({ data: { configured: true, dailySummaryTime: "08:30" } }),
    ),
  );
}

describe("SettingsPage", () => {
  it("shows telegram status and a success toast on test send", async () => {
    mockBaseEndpoints();
    server.use(
      http.post("*/api/internal/v1/telegram/test", () =>
        HttpResponse.json({ data: { ok: true } }),
      ),
    );
    const user = userEvent.setup();
    render(<SettingsPage />);

    expect(await screen.findByText(/настроено/i)).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: /отправить тест/i }));
    expect(await screen.findByText(/тестовое сообщение отправлено/i)).toBeInTheDocument();
  });

  it("shows an error toast when the test send fails", async () => {
    mockBaseEndpoints();
    server.use(
      http.post("*/api/internal/v1/telegram/test", () =>
        HttpResponse.json({ data: { ok: false, error: "boom" } }),
      ),
    );
    const user = userEvent.setup();
    render(<SettingsPage />);

    await screen.findByText(/настроено/i);
    await user.click(screen.getByRole("button", { name: /отправить тест/i }));
    expect(
      await screen.findByText(/не удалось отправить тестовое сообщение/i),
    ).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run, expect failure**

Run: `cd /home/pavel/projects/personal/hestia/frontend && bun run test:run src/features/settings/SettingsPage.test.tsx`
Expected: FAIL (old page has no telegram status / test button wired).

- [ ] **Step 3: Rewrite `SettingsPage.tsx`**

```tsx
import { useTranslation } from "react-i18next";
import toast from "react-hot-toast";
import { LanguageSwitcher } from "../../components/LanguageSwitcher";
import {
  useCategories,
  useCreateCategory,
  useDeleteCategory,
  useRenameCategory,
} from "../../api/queries/categories";
import {
  useCreateLocation,
  useDeleteLocation,
  useLocations,
  useRenameLocation,
} from "../../api/queries/locations";
import {
  useSendTelegramTest,
  useTelegramStatus,
} from "../../api/queries/telegram";
import { ManagedList } from "./ManagedList";

export function SettingsPage(): React.ReactElement {
  const { t } = useTranslation();

  const locations = useLocations();
  const createLocation = useCreateLocation();
  const renameLocation = useRenameLocation();
  const deleteLocation = useDeleteLocation();

  const categories = useCategories();
  const createCategory = useCreateCategory();
  const renameCategory = useRenameCategory();
  const deleteCategory = useDeleteCategory();

  const telegram = useTelegramStatus();
  const sendTest = useSendTelegramTest();

  const onTest = async () => {
    try {
      const result = await sendTest.mutateAsync();
      if (result?.ok) toast.success(t("settings.telegramTestOk"));
      else toast.error(t("settings.telegramTestFailed"));
    } catch {
      toast.error(t("settings.telegramTestFailed"));
    }
  };

  return (
    <div className="p-8">
      <div className="mb-6">
        <h2 className="text-3xl font-bold text-stone-800">{t("settings.title")}</h2>
        <p className="text-stone-500 mt-1">{t("settings.subtitle")}</p>
      </div>

      <div className="max-w-2xl space-y-6">
        <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
          <h3 className="font-semibold text-stone-800 mb-4">{t("settings.language")}</h3>
          <LanguageSwitcher />
        </div>

        <ManagedList
          title={t("settings.locations")}
          items={locations.data ?? []}
          onAdd={(name) => createLocation.mutateAsync(name)}
          onRename={(id, name) => renameLocation.mutateAsync({ id, name })}
          onDelete={(id) => deleteLocation.mutateAsync(id)}
        />

        <ManagedList
          title={t("settings.categories")}
          items={categories.data ?? []}
          onAdd={(name) => createCategory.mutateAsync(name)}
          onRename={(id, name) => renameCategory.mutateAsync({ id, name })}
          onDelete={(id) => deleteCategory.mutateAsync(id)}
        />

        <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
          <h3 className="font-semibold text-stone-800 mb-4">{t("settings.telegram")}</h3>
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-stone-700">
                {telegram.data?.configured
                  ? t("settings.telegramConfigured")
                  : t("settings.telegramNotConfigured")}
              </span>
              {telegram.data?.dailySummaryTime && (
                <span className="text-sm text-stone-500">
                  {t("settings.telegramDailyTime")}: {telegram.data.dailySummaryTime}
                </span>
              )}
            </div>
            <button
              type="button"
              onClick={onTest}
              disabled={!telegram.data?.configured || sendTest.isPending}
              className="px-4 py-2 text-sm rounded-lg bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-40"
            >
              {t("settings.telegramSendTest")}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
```

> Mutation/error toasts for location/category CRUD: `mutateAsync` rejects on 409 (in-use / name-taken). Optionally wrap the `onAdd`/`onDelete` handlers in try/catch + `toast.error` — keep minimal; the delete button is already disabled for in-use items so the common 409 path is prevented in the UI.

- [ ] **Step 4: Verify a Toaster is mounted**

Check `frontend/src/main.tsx` (or `App.tsx`) renders `<Toaster />` from `react-hot-toast`. If not present, add `<Toaster position="top-right" />` once near the app root.
Run: `cd /home/pavel/projects/personal/hestia/frontend && grep -rn "Toaster" src/main.tsx src/App.tsx`
Expected: a match. If none, add it before proceeding.

- [ ] **Step 5: Run the test, expect pass**

Run: `cd /home/pavel/projects/personal/hestia/frontend && bun run test:run src/features/settings/SettingsPage.test.tsx`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
cd /home/pavel/projects/personal/hestia
git add frontend/src/features/settings/SettingsPage.tsx frontend/src/features/settings/SettingsPage.test.tsx frontend/src/main.tsx frontend/src/App.tsx
git commit -s -m "feat(settings): rewrite Settings page (locations/categories CRUD, telegram test)"
```

---

## Task 14: Frontend gate

- [ ] **Step 1: Lint + type-check**

Run: `cd /home/pavel/projects/personal/hestia/frontend && bun run check`
Expected: biome + tsc clean. Fix any issues (unused imports from the old page, etc.).

- [ ] **Step 2: Full test run**

Run: `cd /home/pavel/projects/personal/hestia/frontend && bun run test:run`
Expected: all tests pass (existing + ManagedList + SettingsPage).

- [ ] **Step 3: Commit any fixups**

```bash
cd /home/pavel/projects/personal/hestia
git add frontend/src
git commit -s -m "style(settings): satisfy frontend check + test gates" || echo "nothing to commit"
```

---

## Task 15: Manual verification & finish

- [ ] **Step 1: Manually verify the page** (optional but recommended)

With backend up and the Vite dev server running, open Settings: add/rename/delete a location and category (delete disabled when in use), switch language, and click **Отправить тест** with real Telegram creds in `.env.local` to confirm a real message arrives.

- [ ] **Step 2: Open the PR**

```bash
cd /home/pavel/projects/personal/hestia
gh auth switch -u ratchet27
git push -u origin feat/settings-rework
gh pr create --base master --head feat/settings-rework \
  --title "feat(settings): minimal config-only Settings page" \
  --body "Implements docs/superpowers/specs/2026-06-04-settings-page-design.md — Locations & Categories CRUD (delete-when-empty), Telegram status + real test send. Removes Profile, fake toggles, and data export/import/clear. Version display deferred."
```

---

## Notes for the implementer

- **Entity setters:** `Location`/`Category` may only expose getters today. Add a `setName(string): void` (or `static fn`/fluent matching the entity's style) if missing — Tasks 5/6 depend on it.
- **No `git add -A`:** `make lint` rewrites files; stage explicit paths.
- **Generated names:** Task 9 lists expected Orval function names derived from route names. If they differ, read the generated file and use the real export — do not rename routes after the fact without re-checking the frontend imports.
- **Telegram test is real:** `/telegram/test` always sends synchronously; there is intentionally no automated test for the send path (covered by existing `TelegramSenderTest` et al.).
