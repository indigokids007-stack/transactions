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

## Ownership

Owned by the Cara team.
