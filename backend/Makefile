.PHONY: up down lint test

up:
	docker compose up -d

down:
	docker compose down

lint:
	docker compose exec php vendor/bin/rector
	mago format
	mago lint
	mago analyze
	docker compose exec php vendor/bin/phpstan analyse

test:
	docker compose exec php bin/phpunit
