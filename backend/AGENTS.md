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

### Errors

Every domain error is an `ApiException` carrying an RFC 7807 `ApiProblem`:
`title` (generic, human), `type` (stable SCREAMING_SNAKE code the frontend matches on),
`code` (HTTP status), optional `detail` (specifics for this occurrence, e.g. "only 2
available") and flat snake_case `extraData`. Rules:

- "Not found" errors extend `EntityNotFoundException` and only supply title + type.
- Use `Response::HTTP_*` constants, never bare ints. 404 = missing, 409 = the current
  state conflicts with a well-formed request (name taken, in use, insufficient stock,
  not cookable), 422 = a reference in the payload does not resolve.
- Put per-occurrence text in `detail`, not in `extraData['message']`; the frontend
  already prefers `detail` for toasts.
- Validation failures (`VALIDATION_ERROR`, 422) list `errors: [{property, violation}]` with
  `property` in the **wire** (snake_case) name, not the DTO property; the frontend maps
  it straight onto form fields.
- Never echo `\Throwable::getMessage()` to the client (see `TelegramController::test`):
  map to a fixed code and let the logger keep the detail.
- Exception classes are `final`.

### Response mapping

Turn entities into response DTOs using exactly one of these, by case:

1. **Flat entity → DTO, no computed fields:** Symfony ObjectMapper `#[Map]` on the
   DTO (e.g. `CategoryResponse`, `LocationResponse`, `TaskResponse`, `ProductResponse`).
2. **Entity → DTO with computed/derived fields:** a pure static factory
   `DTO::fromEntity(Entity $e, …scalars): self` on the DTO (e.g.
   `StockEntryResponse`, `ExpiringEntryResponse`, `ShoppingItemResponse`). Keep the
   factory free of services — if a value needs a collaborator (e.g.
   `days_until_expiry` needs `HouseholdCalendar`), the **service** computes the
   scalar and passes it in. This keeps factories unit-testable with no container.
3. **DTO assembled from queries / aggregates / multiple sources** (no single source
   entity): build it in the **service** (e.g. `StockSummaryResponse`,
   `ProductSummaryResponse`). No `#[Map]`, no `fromEntity`.

**Never** build a response array inline in a controller — controllers return DTOs.
Constructing a nested DTO via its constructor inside a factory/service is composition,
not a fourth mechanism.

Date formatting: `DateTimeImmutable` fields serialize as ISO-8601/ATOM via
`DateTimeImmutableNormalizer`; date-only fields (e.g. `best_before`) are formatted
`Y-m-d` in the factory/service. Keep that distinction.

Opportunistic migration (not yet converted): `RecipeService::toResponse` (it is a
private method building the DTO plus computed stock fields; case 3 in spirit).

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

PHPUnit 13 via Symfony test-pack. Functional tests use Zenstruck Foundry factories +
`ResetDatabase`; the API sits behind an authenticated firewall, so functional tests that hit
`/api/internal/v1/...` must authenticate in `setUp` (`$this->client->loginUser(UserFactory::createOne())`)
and attach a CSRF token on writes (see `ApiTestTrait`).

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

**When to run it (finishing check).** CI does **not** run Infection — `make lint` and
`make test` are the only automated gates. So after any substantial change to business
logic (anything in `src/Service` or `src/Entity` — the configured scope in
`infection.json5`), run a full `make mutate` **once** before considering the work done,
in addition to `make lint`/`make test`. It catches tests that pass but don't actually
assert the behavior they cover. A full pass takes ~5 min; the MSI floor is **90%**
(`minMsi` / `minCoveredMsi`) and the run fails if you drop below it. Trivial changes
(docs, config, plain controller wiring with no new logic) don't need it.

For fast iteration on a single file, scope it: `vendor/bin/infection --filter=src/Service/File.php`.
Reserve the full `make mutate` for the final gate.

## Code Quality

**You MUST run `make lint` before claiming any backend work is complete.** It runs the full
gate in order — `rector → mago format → mago lint → mago analyze → phpstan` — which is a
**superset of CI's Code Quality job** (`mago format --check`, `mago lint`, `mago analyze`).

**Do NOT substitute a subset** (e.g. bare `mago format && mago lint`). That skips
`mago analyze`, so analyzer-only errors (`invalid-return-statement`, `mixed-argument`, …)
pass locally and then fail CI. `make lint` needs Docker up (rector + phpstan run in the
container; mago runs on the host, never in Docker).

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

mago scans `src` and `tests` only (see `mago.toml` `[source] paths`) — it does NOT touch
`config/`. For genuine vendor/framework type mismatches the analyzer can't reconcile,
suppress with an inline `// @mago-ignore analysis:<rule>` on the line above (the codebase
already does this, e.g. `analysis:mixed-return-statement` for Doctrine `getResult()`).
Prefer a real fix (e.g. an explicit `(string)` cast) when one is clean.

### PHPStan (static analysis)

```bash
docker compose exec php vendor/bin/phpstan analyse
```

### Quick Check (all tools)

Just use `make lint` — it runs all of the above in the correct order:

```bash
make lint
```

Note: `config/reference.php` is a Symfony-generated IDE-autocomplete dump (non-deterministic
union ordering, regenerated on every kernel boot). It is **gitignored** — never commit it or
treat its churn as a real change.

## Claude Code Notes

### Permission Issues

Docker creates files as root. If you encounter `EACCES: permission denied` errors when writing files:

1. **Stop execution immediately**
2. Ask the user to run: `sudo chown -R $USER:$USER /home/pavel/projects/personal/hestia/backend`
3. Wait for confirmation before continuing

Do NOT work around this by writing files through Docker exec - it masks the underlying issue and creates inconsistent file ownership.
