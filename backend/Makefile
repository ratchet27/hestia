.PHONY: install up down lint test mutate

# Clean local (re)install: drops every container AND volume (database, broker,
# Caddy certs), rebuilds the dev image, migrates (entrypoint), seeds defaults
# and the dev login (SeedCommand::DEV_USERNAME / DEV_PASSWORD).
install:
	docker compose down --volumes --remove-orphans
	docker compose build --pull
	# --wait fails on services without a healthcheck (messenger), so wait on php
	# (which pulls database + rabbitmq) and start the rest afterwards.
	docker compose up -d --wait php
	docker compose up -d
	docker compose exec php bin/console app:seed --with-dev-user

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
