# Transactions

Cara staff record their expenses and incomes through a Telegram bot or mini app, and this Laravel backend stores, scopes and reports on those entries.

## Running locally

A clean clone needs nothing but Docker and these commands:

```bash
make up       # build and start every container, then wait until the app is healthy
make smoke    # ask the running app for a few pages over HTTP and check they answer
make test     # run the Pest test suite
make analyze  # run PHPStan (Larastan) at level 6
make pint     # check and fix code style
```

`make up` is the whole setup. The app container's entrypoint (`docker/app/entrypoint.sh`) creates `.env` from `.env.example` when it is missing, runs `composer install` when `vendor/` is empty, generates the application key when it is blank, and runs migrations. The first run takes a few minutes because of `composer install`; later runs skip every step that is already done. `make up` then blocks until the app container reports healthy, so when it returns the application really is serving.

Three services come up: `app` (the HTTP server), `db` (PostgreSQL 17) and `scheduler` (`php artisan schedule:work`, which is what runs the hourly `transactions:prune-drafts` sweep and the daily token prune). All three restart unless stopped.

`make smoke` is not part of `make test` and does not overlap with it. The suite forces its own database settings in `phpunit.xml`, so it passes whether or not the application you can actually visit is wired to the database; `make smoke` asks the running container over real HTTP instead.

### Configuration

`.env.example` is the single source of truth for configuration, including the database. It deliberately does not appear as `environment:` entries in `docker-compose.yml`: `php artisan serve` passes only a short whitelist of environment variables through to the PHP process it starts, and `DB_*` is not on that list, so database settings given to the container never reach the process answering requests.

Its defaults are the safe ones (`APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`), so a copy that reaches a server does not serve stack traces. For local work, set `APP_DEBUG=true` and `LOG_LEVEL=debug` in your own `.env` after it has been created.

The Telegram webhook (`/telegram/webhook`) needs a public HTTPS tunnel in development, since Telegram cannot reach `localhost`. Use a tool such as ngrok or Cloudflare Tunnel and point the bot's webhook URL at the tunnel.

## Telegram bot setup

1. Create a bot with [@BotFather](https://t.me/BotFather) and copy the token it gives you.
2. Set `TELEGRAM_BOT_TOKEN` (the token from step 1) and `TELEGRAM_WEBHOOK_SECRET` (any random string you choose) in `.env`.
3. Optionally set `ADMIN_TELEGRAM_ID` to your own numeric Telegram id so `db:seed` creates you as an admin (see "Admin panel login" below).
4. Expose the app over HTTPS: Telegram will not deliver updates to a plain `http://` or `localhost` URL. In development, run a tunnel (ngrok, Cloudflare Tunnel) and set `APP_URL` to the tunnel's HTTPS address.
5. Register the webhook with Telegram:
   ```bash
   make artisan cmd="telegram:set-webhook"
   ```
   This points Telegram at `{APP_URL}/telegram/webhook` with the `TELEGRAM_WEBHOOK_SECRET` as the shared secret, and exits non-zero if Telegram refuses the registration.
6. Open the bot in Telegram and send an amount (for example `120000 taksi`) to record your first entry.

## Admin panel login

The panel at `/admin` uses Filament's standard email and password login; staff otherwise authenticate through Telegram only and have no password. To get a working admin login, set all three of `ADMIN_TELEGRAM_ID`, `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env` **before the first time** `php artisan db:seed` creates that user, then run `make artisan cmd="db:seed"` (see the warning below before reaching for `make fresh` instead). `ReferenceDataSeeder` then creates that user as an active admin with the given email and a bcrypt hash of the given password, and you can sign in at `/admin` with that email and password.

`ReferenceDataSeeder` never edits a user that already exists, only creates one: if a user with `ADMIN_TELEGRAM_ID` is already in the database (because it seeded without credentials before, or because that person had already signed up through the bot), setting `ADMIN_EMAIL`/`ADMIN_PASSWORD` and running `db:seed` again is a no-op, and the seeder says so ("already exists... left unchanged") rather than claiming it worked. To grant that existing user a login, set their email and password directly, for example through the panel (once another admin exists) or `php artisan tinker`. Telegram-based login for the panel itself is not built yet.

> **Warning:** `make fresh` runs `migrate:fresh --seed`, which drops every table before re-migrating and re-seeding. It is a development-only, data-destroying command; never run it against a database you want to keep.

## Test database

The test suite always runs against a separate `transactions_test` database on the same Postgres server, never against the `transactions` development database, since the suite uses `RefreshDatabase` and would otherwise wipe development data on every run. This holds regardless of how the suite is invoked, `make test`, `php artisan test`, or `vendor/bin/pest` directly inside the container, because `tests/bootstrap.php` (the PHPUnit `bootstrap` entry point, shared by all three) forces `DB_DATABASE=transactions_test` before the application boots. `make up` and `make test` both depend on the `test-db` Makefile target, which creates `transactions_test` if it does not already exist, so no manual setup step is required.

## Mini app

The Telegram mini app in `mini-app/` is a separate React SPA that talks to this API. Unlike the backend above, it runs directly on the host with Node 22 rather than in Docker:

```bash
make app-dev    # start the Vite dev server
make app-test   # run the Vitest suite
make app-build  # produce a production build
make app-lint   # type-check with tsc
```

## Ownership

Owned by the Cara team.
