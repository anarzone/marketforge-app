.PHONY: up down restart build sh logs ps test stan pint migrate fresh seed install

# --- Docker lifecycle ---
up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart app

build:
	docker compose build --no-cache

ps:
	docker compose ps

logs:
	docker compose logs -f app

sh:
	docker compose exec app bash

# --- Composer / install ---
install:
	docker compose exec app composer install

# --- Quality ---
test:
	docker compose exec app php artisan test --compact

stan:
	docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M

pint:
	docker compose exec app vendor/bin/pint --format agent

# --- Database ---
migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh

seed:
	docker compose exec app php artisan db:seed
