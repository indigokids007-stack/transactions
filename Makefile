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
	$(DC) exec -T db sh -c "until pg_isready -U transactions >/dev/null 2>&1; do sleep 1; done; psql -U transactions -lqt | cut -d '|' -f 1 | grep -qw transactions_test || createdb -U transactions -O transactions transactions_test"

test: test-db
	$(PHP_TEST) artisan test $(args)

analyze:
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

pint:
	$(PHP) vendor/bin/pint --dirty
