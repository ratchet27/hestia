.PHONY: up down lint test mutate

up:
	docker compose up -d

down:
	docker compose down

lint:
	docker compose exec php vendor/bin/rector
	mago format
	mago lint
	mago analyze
	docker compose exec php vendor/bin/phpstan analyse --memory-limit=512M

test:
	docker compose exec php bin/phpunit

# Coverage runs under pcov, not Xdebug (faster initial coverage collection).
# - XDEBUG_MODE=coverage makes Xdebug "active", so Infection's bundled
#   composer/xdebug-handler restarts the process WITHOUT Xdebug loaded
#   (it skips that restart when the mode is off). Xdebug then plays no part in
#   the run and the notice correctly reads "running with PCOV enabled".
# - --initial-tests-php-options enables pcov (dormant by default, see
#   frankenphp/conf.d/20-app.dev.ini) for the single initial coverage subprocess.
mutate:
	docker compose exec -e XDEBUG_MODE=coverage php vendor/bin/infection --show-mutations \
		--initial-tests-php-options="-d pcov.enabled=1"
