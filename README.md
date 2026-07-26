# Transactions

Cara staff record their expenses and incomes through a Telegram bot or mini app, and this Laravel backend stores, scopes and reports on those entries.

## Running locally

```bash
make up       # build and start the app and database containers
make migrate  # run database migrations
make test     # run the Pest test suite
make analyze  # run PHPStan (Larastan) at level 6
make pint     # check and fix code style
```

The Telegram webhook (`/telegram/webhook`) needs a public HTTPS tunnel in development, since Telegram cannot reach `localhost`. Use a tool such as ngrok or Cloudflare Tunnel and point the bot's webhook URL at the tunnel.

## Test database

The test suite always runs against a separate `transactions_test` database on the same Postgres server, never against the `transactions` development database, since the suite uses `RefreshDatabase` and would otherwise wipe development data on every run. This holds regardless of how the suite is invoked, `make test`, `php artisan test`, or `vendor/bin/pest` directly inside the container, because `tests/bootstrap.php` (the PHPUnit `bootstrap` entry point, shared by all three) forces `DB_DATABASE=transactions_test` before the application boots. `make up` and `make test` both depend on the `test-db` Makefile target, which creates `transactions_test` if it does not already exist, so no manual setup step is required.

## Ownership

Owned by the Cara team.
