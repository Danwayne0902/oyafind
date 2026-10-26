# OyaFind operations. Run from this directory on the server.

COMPOSE ?= docker compose
ARTISAN ?= $(COMPOSE) exec -T app php artisan

.DEFAULT_GOAL := help

.PHONY: help up down restart ps logs deploy seed migrate shell review pending verify backup restore-db

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Build and start everything
	$(COMPOSE) up -d --build

down: ## Stop everything (data is kept)
	$(COMPOSE) down

restart: ## Recreate containers without rebuilding images
	$(COMPOSE) up -d --force-recreate

ps: ## Show container status
	$(COMPOSE) ps

logs: ## Follow logs from every container
	$(COMPOSE) logs -f --tail=100

deploy: ## Pull the latest code, rebuild and apply migrations
	git -C .. pull --ff-only
	$(COMPOSE) up -d --build
	$(ARTISAN) migrate --force

seed: ## Load categories, locations and the source registry (idempotent)
	$(ARTISAN) db:seed --force

migrate: ## Apply pending migrations
	$(ARTISAN) migrate --force

shell: ## Open a shell inside the application container
	$(COMPOSE) exec app sh

review: ## List listings waiting for a staff decision
	$(ARTISAN) opportunities:pending --status=pending

pending: review ## Alias for review

verify: ## List approved listings waiting for source checks
	$(ARTISAN) opportunities:pending --status=reviewed

backup: ## Write a database and storage backup now
	./backup.sh

restore-db: ## Restore a dump: make restore-db FILE=/var/backups/oyafind/db-....sql.gz
	@test -n "$(FILE)" || (echo "set FILE=<path to .sql.gz>" && exit 1)
	gunzip -c "$(FILE)" | $(COMPOSE) exec -T mysql sh -c \
		'exec mysql -u root -p"$$MYSQL_ROOT_PASSWORD" "$$MYSQL_DATABASE"'
