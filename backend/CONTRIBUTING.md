# Backend Contributing Guide

This document outlines the conventions and patterns used in the Hestia backend.

## Entities

### UUID Primary Keys

All entities use UUID v7 for primary keys. Use the `UuidType::NAME` constant for type declarations:

```php
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Id]
#[ORM\Column(type: UuidType::NAME, unique: true)]
private Uuid $id;

public function __construct()
{
    $this->id = Uuid::v7();
}
```

### Timestamps

- `createdAt` - Required on entities that track creation time. Set in constructor.
- `updatedAt` - Nullable. Only set when entity is actually updated (via `#[ORM\PreUpdate]`).

```php
#[ORM\Column]
private \DateTimeImmutable $createdAt;

#[ORM\Column(nullable: true)]
private ?\DateTimeImmutable $updatedAt = null;

public function __construct()
{
    $this->createdAt = new \DateTimeImmutable();
}

#[ORM\PreUpdate]
public function updateTimestamp(): void
{
    $this->updatedAt = new \DateTimeImmutable();
}
```

**Note:** Static reference data entities (like Category, Location) may omit timestamps in v1.

## DTOs

### Request DTOs

Location: `src/Request/`

Request DTOs are readonly classes that validate incoming request data:

```php
namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProductRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $category_id,
    ) {}
}
```

### Response DTOs

Location: `src/Response/<Domain>/`

Response DTOs are readonly classes that structure API responses:

```php
namespace App\Response\Product;

use App\Entity\Product;

final readonly class ProductResponse
{
    public function __construct(
        public string $id,
        public string $name,
        // ...
    ) {}

    public static function fromEntity(Product $product): self
    {
        return new self(
            id: (string) $product->getId(),
            name: $product->getName(),
            // ...
        );
    }
}
```

**Important:** Do not add `toArray()` methods to DTOs. Symfony's serializer handles JSON conversion automatically when you pass objects to `$this->json()`.

## Services

Services accept DTOs directly, not arrays:

```php
// Good
public function createProduct(CreateProductRequest $request): Product
{
    $product->setName($request->name);
}

// Bad - don't use arrays
public function createProduct(array $data): Product
{
    $product->setName($data['name']);
}
```

## Controllers

### Response Serialization

Pass response objects directly to `$this->json()`:

```php
// Good
return $this->json([
    'data' => ProductResponse::fromEntity($product),
]);

// Bad - don't call toArray()
return $this->json([
    'data' => ProductResponse::fromEntity($product)->toArray(),
]);
```

### OpenAPI Documentation

Use OpenAPI attributes to document API endpoints:

```php
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Products')]
final class ProductController extends AbstractController
{
    #[Route('/products', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'List of products')]
    public function list(): JsonResponse
    {
        // ...
    }

    #[Route('/products', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Product created')]
    #[OA\Response(response: 400, description: 'Invalid input')]
    public function create(
        #[MapRequestPayload] CreateProductRequest $request,
    ): JsonResponse {
        // ...
    }
}
```

## Directory Structure

```
src/
├── Controller/
│   └── Api/
│       └── Internal/
│           └── V1/           # Versioned internal API controllers
├── Entity/                   # Doctrine entities
├── Repository/               # Doctrine repositories
├── Request/                  # Request DTOs
├── Response/
│   └── <Domain>/            # Response DTOs grouped by domain
├── Service/                  # Business logic services
└── Factory/                  # Foundry factories (dev/test only)
```
