# Hestia Backend

Symfony 8.0 API backend running on FrankenPHP with PostgreSQL.

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
docker compose exec php bin/phpunit

# Run specific test
docker compose exec php bin/phpunit tests/SomeTest.php
```

## Code Quality

Mago for linting, formatting, and static analysis. Runs locally (not in container).

```bash
# Lint
mago lint src/

# Format
mago fmt src/

# Check (no changes)
mago fmt --check src/

# Static analysis
mago analyze src/
```

## Claude Code Notes

### Permission Issues

Docker creates files as root. If you encounter `EACCES: permission denied` errors when writing files:

1. **Stop execution immediately**
2. Ask the user to run: `sudo chown -R $USER:$USER /home/pavel/projects/hestia/backend`
3. Wait for confirmation before continuing

Do NOT work around this by writing files through Docker exec - it masks the underlying issue and creates inconsistent file ownership.
