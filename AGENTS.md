# AGENTS.md

Rules for anyone (human or agent) working on this repository. Restates the Global Constraints from the implementation plan at `docs/superpowers/plans/2026-07-26-transactions-backend.md`.

## Global constraints

- PHP 8.4, Laravel 12, PostgreSQL 17, Filament v4, Pest 4. Versions are read from the manifest once created, never hardcoded elsewhere.
- The host has no `php` and no `composer`. Every PHP, Artisan, Composer and test command runs inside the `app` container through the `Makefile`.
- Money is stored as `amount_minor` (`bigint`) plus an ISO 4217 `currency` code. Never float, never `double`.
- Aggregates are always grouped by currency. Amounts of different currencies are never summed.
- Authorization scope is resolved server side in `TransactionScope`. A client supplied `user_id` or `department_id` is a filter inside the caller's scope, never a way to widen it.
- `transactions.department_id` is a snapshot written from the author at creation time and is never recalculated afterwards.
- Follow the Spatie PHP and Laravel guidelines: typed properties, constructor property promotion, early returns, no `else`, string interpolation, array notation in validation rules, no comments that restate code. Migrations contain an `up` method only.
- No new Composer or NPM dependency beyond those named in Task 1 without explicit human approval.
- Bot and API user facing strings live in `lang/{uz,ru,en}` and are kept in sync.
