DC = docker compose
PHP = $(DC) exec -T app php
PHP_TEST = $(DC) exec -T -e DB_DATABASE=transactions_test app php
COMPOSER = $(DC) exec -T app composer

.PHONY: up down shell artisan composer migrate fresh test test-db smoke wait-app analyze pint

up:
	$(DC) up -d --build
	$(MAKE) wait-app
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
	$(PHP) artisan migrate --force

fresh:
	$(PHP) artisan migrate:fresh --seed --force

# The container entrypoint installs dependencies, writes .env, generates the key and
# migrates on first boot, so a clean clone spends a few minutes here and seconds after.
wait-app:
	@i=0; until [ "$$($(DC) ps --format '{{.Health}}' app 2>/dev/null)" = "healthy" ]; do \
		i=$$(( i + 1 )); \
		if [ "$$i" -ge 300 ]; then echo "app not healthy after 300s" >&2; $(DC) logs --tail=50 app >&2; exit 1; fi; \
		sleep 1; \
	done

# The suite forces its own database settings in phpunit.xml, so it cannot see whether the
# served application is wired to Postgres or has quietly fallen back to SQLite. This asks
# the running container over real HTTP, on routes that need a database to answer at all.
smoke:
	@for path in /api/health / /admin/login; do \
		code=$$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8000$$path); \
		if [ "$$code" != "200" ]; then echo "smoke: $$path returned $$code, expected 200" >&2; exit 1; fi; \
		echo "smoke: $$path 200"; \
	done

test-db:
	$(DC) exec -T db sh -c 'i=0; until pg_isready -U transactions >/dev/null 2>&1; do i=$$(( i + 1 )); if [ "$$i" -ge 30 ]; then echo "db not ready after 30s" >&2; exit 1; fi; sleep 1; done; psql -U transactions -d transactions -v ON_ERROR_STOP=1 -f /create-test-database.sql >/dev/null'

test: test-db
	$(PHP_TEST) artisan test $(args)

analyze:
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

pint:
	$(PHP) vendor/bin/pint --dirty
