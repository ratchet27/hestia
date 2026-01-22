# Hestia Backend

Symfony 8.0 API backend running on FrankenPHP with PostgreSQL.

## Session Startup (REQUIRED)

**At the start of EVERY session, you MUST:**

1. Check if Docker containers are running: `docker compose ps`
2. If not running, start them: `make up` (or `docker compose up -d`)
3. If containers are stuck (e.g., database waiting indefinitely), restart:
   ```bash
   make down && make up
   ```
4. If port 5432 is in use by host PostgreSQL, ask user to stop it first

**Do NOT proceed with backend work until containers are healthy.**

## Tech Stack

- PHP 8.4+ with Symfony 8.0
- FrankenPHP (Caddy-based PHP server)
- PostgreSQL 18
- Docker Compose for development

## Development

```bash
# Start containers
docker compose up -d

# View logs
docker compose logs -f php

# Stop containers
docker compose down
```

Application runs at https://localhost (self-signed cert).

## Key Paths

```
src/
├── Controller/     # HTTP controllers
├── Entity/         # Doctrine entities
├── Repository/     # Doctrine repositories
└── Service/        # Business logic

config/
├── packages/       # Bundle configuration
├── routes.yaml     # Route definitions
└── services.yaml   # Service container config
```

## Conventions

### Controllers
- One controller per resource
- Use `#[Route]` attributes
- Return JSON responses via `JsonResponse` or serializer

#### UUID Route Parameters

Use Symfony's `Uuid` type-hint with `Requirement::UUID_V7` for automatic validation:

```php
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/products/{uuid}', requirements: ['uuid' => Requirement::UUID_V7], methods: ['GET'])]
public function show(Uuid $uuid): JsonResponse
{
    // $uuid is already a Uuid object - no manual validation needed
}
```

**Do NOT** use raw regex patterns or manual `Uuid::fromString()` with try/catch.

Invalid UUIDs return 404 (route not matched) rather than 400.

#### OpenAPI Documentation

Use HTTP method attributes for operation summary/description, and `#[OA\Response]` with `content` for response schemas:

```php
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Get(summary: 'Get product', description: 'Returns a single product by its UUID.')]
#[OA\Response(response: 200, description: 'Product details', content: new Model(type: ProductResponse::class))]
#[OA\Response(response: 404, description: 'Product not found', content: new Model(type: ApiProblem::class))]
public function show(Uuid $uuid): JsonResponse
```

- Use `#[OA\Get]`, `#[OA\Post]`, `#[OA\Put]`, `#[OA\Delete]` with `summary` and `description`
- Use `Model(type: ...)` to reference response/error classes
- Use `ApiProblem::class` for all error responses (RFC 7807)
- Path parameters are auto-documented from route - no need for `#[OA\Parameter]`

### Services
- Constructor injection (autowired)
- Interfaces for external dependencies
- Suffix with purpose: `UserService`, `TokenGenerator`

### Entities
- Doctrine ORM with attributes
- Repository pattern for queries
- UUID primary keys preferred
- For unique fields: use BOTH `#[UniqueEntity]` (validation) AND `#[ORM\Column(unique: true)]` (database constraint)

## Commands

**IMPORTANT: NEVER run PHP or Composer commands directly on the host machine.** Always use `docker compose exec php` to run commands inside the container.

Use Docker for all composer commands:

```bash
docker compose exec php composer <command>
```

```bash
# Run inside php container
docker compose exec php bin/console <command>

# Common commands
bin/console make:controller
bin/console make:entity
bin/console doctrine:migrations:migrate
bin/console cache:clear
```

## Database

PostgreSQL connection configured via `DATABASE_URL` env var.

```bash
# Access database
docker compose exec database psql -U app -d app
```

## Testing

PHPUnit 12 via Symfony test-pack.

```bash
# Run tests
make test

# Run specific test
docker compose exec php bin/phpunit tests/SomeTest.php
```

### Mutation Testing

Infection PHP for mutation testing - verifies tests actually catch code changes.

```bash
make mutate
```

Reports saved to `var/infection.log` and `var/infection.html`.

## Code Quality

Tools configured: Rector, Mago, PHPStan. Run in this order (file-modifying first, then analysis).

### Rector (refactoring)

```bash
docker compose exec php vendor/bin/rector
```

### Mago (format, lint, analyze)

Runs locally (not in container). Uses `mago.toml` for configuration.

```bash
mago format           # Auto-format code
mago format --check   # Check formatting (CI)
mago lint             # Check for issues
mago analyze          # Static analysis
```

### PHPStan (static analysis)

```bash
docker compose exec php vendor/bin/phpstan analyse
```

### Quick Check (all tools)

```bash
docker compose exec php vendor/bin/rector && \
mago format && mago lint && mago analyze && \
docker compose exec php vendor/bin/phpstan analyse
```

## Claude Code Notes

### Permission Issues

Docker creates files as root. If you encounter `EACCES: permission denied` errors when writing files:

1. **Stop execution immediately**
2. Ask the user to run: `sudo chown -R $USER:$USER /home/pavel/projects/personal/hestia/backend`
3. Wait for confirmation before continuing

Do NOT work around this by writing files through Docker exec - it masks the underlying issue and creates inconsistent file ownership.
