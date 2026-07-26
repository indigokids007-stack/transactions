DC = docker compose
PHP = $(DC) exec -T app php
PHP_TEST = $(DC) exec -T -e DB_DATABASE=transactions_test app php
COMPOSER = $(DC) exec -T app composer

.PHONY: up down shell artisan composer migrate fresh test test-db analyze pint

up:
	$(DC) up -d --build
	$(MAKE) test-db

down:
	$(DC) down

shell:
	$(DC) exec app sh

artisan:
	$(PHP) artisan $(cmd)

composer:
	$(COMPOSER) $(cmd)

migrate:
	$(PHP) artisan migrate

fresh:
	$(PHP) artisan migrate:fresh --seed

test-db:
	$(DC) exec -T db sh -c 'i=0; until pg_isready -U transactions >/dev/null 2>&1; do i=$$(( i + 1 )); if [ "$$i" -ge 30 ]; then echo "db not ready after 30s" >&2; exit 1; fi; sleep 1; done; psql -U transactions -d transactions -v ON_ERROR_STOP=1 -f /create-test-database.sql >/dev/null'

test: test-db
	$(PHP_TEST) artisan test $(args)

analyze:
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

pint:
	$(PHP) vendor/bin/pint --dirty
