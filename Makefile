DC = docker compose
PHP = $(DC) exec -T app php
COMPOSER = $(DC) exec -T app composer

.PHONY: up down shell artisan composer migrate fresh test analyze pint

up:
	$(DC) up -d --build

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

test:
	$(PHP) artisan test $(args)

analyze:
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

pint:
	$(PHP) vendor/bin/pint --dirty
