.PHONY: help build up down restart logs shell install setup migrate fresh test queue ps clean

# Default target — show available commands
help:
	@echo "Available commands:"
	@echo "  make setup     - Full first-time setup (build, up, install, key, migrate)"
	@echo "  make build     - Build the Docker images"
	@echo "  make up        - Start all containers in the background"
	@echo "  make down      - Stop and remove containers (keeps DB data)"
	@echo "  make clean     - Stop and remove containers + volumes (wipes DB data)"
	@echo "  make restart   - Restart all containers"
	@echo "  make install   - composer install inside the app container"
	@echo "  make migrate   - Run database migrations"
	@echo "  make fresh     - Drop all tables and re-run migrations"
	@echo "  make queue     - Start the queue worker (processes uploads)"
	@echo "  make test      - Run the test suite"
	@echo "  make shell     - Open a bash shell in the app container"
	@echo "  make logs      - Tail logs from all containers"
	@echo "  make ps        - List running containers"

# First-time setup: build, start, install deps, generate key, migrate
setup:
	docker compose build
	@echo "Starting containers (waits for MySQL to be healthy)..."
	docker compose up -d --wait
	docker compose exec app composer install
	docker compose exec app cp -n .env.example .env || true
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate
	@echo "Setup complete — app is running at http://localhost:8000"

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

clean:
	docker compose down -v

restart:
	docker compose restart

install:
	docker compose exec app composer install

migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh

queue:
	docker compose exec app php artisan queue:work

test:
	docker compose exec app php artisan test

shell:
	docker compose exec app bash

logs:
	docker compose logs -f

ps:
	docker compose ps
