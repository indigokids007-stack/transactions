# Design: Transactions (Cara staff expense and income ledger)

Date: 2026-07-26
Status: approved design, not yet planned

## Goal

Cara staff register their own expenses and incomes from a Telegram mini app or directly from a chat with the bot, and can look at reports of their own spend for any period. Managers see the reports of their department, the business owner and admins see everything. Sales, stock and order money stay in the existing Cara platform; this system covers the money that platform does not track.

## Scope (in)

- Telegram based authentication (mini app `initData`, bot webhook by `telegram_id`).
- Open registration with admin activation, plus a global toggle to close registration.
- Recording income and expense transactions with amount, currency, date, category, note and admin defined dimensions.
- Admin defined category tree and admin defined dimensions (Branch, Project, Vehicle and so on) with their values.
- Departments as a first class entity used for report scoping.
- Two entry paths (bot quick entry with confirmation, mini app form), one shared write API.
- Edit and delete of own records, with a full append only revision log.
- Reports: period totals with breakdown, trend over time, per staff comparison, filtered transaction list.
- Export of the filtered transaction list to CSV/XLSX.
- Filament admin panel for users, activation, categories, dimensions, settings, all transactions and revision history.
- Languages uz, ru, en.

## Scope (out)

- Cashboxes / accounts ("which pocket the money came from") and any balance arithmetic. Planned for later, see Future compatibility.
- Cash advances (podotchet), reimbursement tracking, settlement of who owes whom.
- Approval workflow for submitted transactions.
- Receipt photo attachments.
- Currency conversion and exchange rates.
- Any integration with the existing Cara sales/stock platform.
- Budgets, limits, financial goals.

## Constraints

- PostgreSQL only. Expected volume is under 10k transactions per month with under 50 staff, so an analytics engine (ClickHouse, Timescale) would be unjustified complexity. Reports are computed live with proper indexes.
- Money is stored as `bigint` minor units plus an ISO currency code, exponent from config. No floats.
- Multi currency without conversion: amounts are never summed across currencies, every aggregate is grouped by currency.
- Stack: Laravel 12 / PHP 8.4, Filament v4, PostgreSQL, React + Vite + TypeScript + Tailwind for the mini app (same shape as the sibling `cara-b2b-bot`).
- Single Laravel application serves the REST API, the Telegram webhook and the Filament admin. The mini app is a separate static build.
- Telegram webhook requires a public HTTPS endpoint (no long polling).

## Architecture

One Laravel application, three entry surfaces on one domain model:

1. REST API (`/api/*`) consumed by the mini app.
2. Telegram webhook (`/telegram/webhook`) for bot quick entry.
3. Filament admin panel for administration and company wide reporting.

The bot does not own any write logic. It builds a draft, and on confirmation calls the same transaction write path that the mini app uses, so validation, scoping, idempotency and the revision log exist in exactly one place.

## Data model

```
users                  id, telegram_id (unique), username, name,
                       role (staff|manager|owner|admin),
                       department_id (nullable), locale (uz|ru|en),
                       status (pending|active|blocked), timestamps

departments            id, name, is_active
department_manager     user_id, department_id            (a manager may cover several)

categories             id, parent_id (tree), name,
                       applies_to (income|expense|both), is_active, sort

dimensions             id, key, name, is_required, is_active, sort
dimension_values       id, dimension_id, name, is_active, sort

transactions           id, user_id (whose spend), department_id (snapshot),
                       type (income|expense), amount_minor (bigint), currency (char3),
                       occurred_on (date), category_id, note,
                       created_by, deleted_at (soft delete), timestamps
                       -- reserved for later: account_id (nullable)

transaction_dimension_values   transaction_id, dimension_id, dimension_value_id
                               unique (transaction_id, dimension_id)

transaction_revisions  id, transaction_id, action (created|updated|deleted|restored),
                       actor_id, snapshot (jsonb), created_at

entry_drafts           id, user_id, payload (jsonb), expires_at, created_at

settings               key, value (jsonb)        -- registration_open, allowed currencies
```

Decisions worth stating explicitly:

- `transactions.department_id` is a snapshot taken from the author at creation time. When a person transfers to another department, past reports stay with the department that actually incurred the spend instead of silently rewriting history.
- Department is a real table and never one of the admin defined dimensions. If manager scope sat on a dimension value, a staff member could edit that value and move records in or out of a manager's view. Authorization must not depend on a user editable label.
- Sticky defaults need no table. Prefill comes from the author's last non deleted transaction.

Indexes: `(user_id, occurred_on)`, `(department_id, occurred_on)`, `(occurred_on)`, `(category_id)`, all partial `WHERE deleted_at IS NULL`; the dimension pivot indexed `(dimension_value_id, transaction_id)`; a unique index backing the write idempotency key.

## Authentication and registration

The mini app posts Telegram `initData`. The server validates the HMAC against the bot token and rejects a stale `auth_date`. The bot webhook identifies the person by `telegram_id` from the update and runs the same status checks.

- A known active user receives an expiring bearer token (Sanctum personal access token).
- An unknown `telegram_id` while `registration_open` is true creates a `pending` user and notifies admins. The person sees a "waiting for approval" screen and no data.
- An unknown `telegram_id` while `registration_open` is false is rejected with "registration closed, contact the administrator" and no user row is created.
- Closing registration never affects existing users; pending ones stay activatable.

Admins activate a pending user in Filament, assign the role and the department.

## Roles and scoping

| Role | Sees | Can administer |
|---|---|---|
| staff | own transactions and own reports | nothing |
| manager | transactions of the departments they manage, including their own | nothing |
| owner | everything, read only, plus export | nothing |
| admin | everything | users, activation, roles, departments, categories, dimensions, settings |

Scope resolution lives in one place server side and is applied identically to list, summary, trend and export: staff resolves to `user_id = self`, manager to `department_id IN (managed departments)`, owner and admin to everything. A `user_id` sent by the client is ignored unless the caller is manager (inside scope), owner or admin. A staff member with no department is visible only to owner and admin.

## API

```
POST   /api/auth/telegram          initData -> token plus user state
GET    /api/bootstrap              category tree, dimensions and values, currencies,
                                   sticky defaults, permissions (one cold start call)
POST   /api/transactions           requires Idempotency-Key
GET    /api/transactions           filters: period, type, category, dimension[key],
                                   currency, user_id, department_id, cursor paging
PATCH  /api/transactions/{id}
DELETE /api/transactions/{id}      soft delete
GET    /api/transactions/{id}/revisions
GET    /api/reports/summary        from, to, group_by=category|dimension:<key>|user|currency
GET    /api/reports/trend          from, to, interval=day|week|month
GET    /api/exports/transactions   csv or xlsx, respects the caller's scope
```

Validation rules: amount greater than zero, currency inside the allowed list, category `applies_to` compatible with the transaction type, every `is_required` dimension present, `occurred_on` not in the future beyond a small tolerance. Writes carry an `Idempotency-Key` backed by a unique index so a double tap in the bot or the mini app cannot produce two rows.

## Entry UX

### Bot (quick path)

A staff member sends a plain message, for example `120000` or `120000 taksi`. The bot parses the amount and the optional note, stores an `entry_draft`, and replies with a preview card built from sticky defaults:

```
Rasxod: 120 000 UZS
Kategoriya: Taksi
Bo'lim: Sotuv, Filial: Chilonzor
Sana: 26.07.2026   Izoh: taksi
[Tasdiqlash] [Kategoriya] [Ilovada ochish] [Bekor]
```

Nothing is written until the person taps confirm. Changing the category swaps in an inline keyboard of the three or four most used categories plus "open in app". Required dimensions, if an admin marked any, are asked as extra keyboard steps before the preview; this is why Filament warns that `is_required` should be used sparingly, since every required dimension costs a tap. Drafts expire and a scheduled job prunes them, so a stale draft cannot be confirmed hours later. Confirmation posts to the same write endpoint using the draft id as the idempotency key, and the reply then offers edit and delete.

An unparseable message gets a short hint, not a wall of instructions.

### Mini app

Amount keypad first and focused, category chips below it, dimensions collapsed under a "Details" section prefilled with sticky defaults, date defaulting to today. Saving is one tap away from a cold open. Telegram theme parameters drive the colors and the Telegram main button acts as save. Language comes from the Telegram `language_code` and is overridable in the profile.

## Reports

All four reports respect the caller's scope and group every aggregate by currency.

1. Period totals and breakdown: income against expense for a period, broken down by category or by any dimension, shown as a chart plus a table.
2. Trend over time: daily, weekly or monthly totals across the period.
3. Per staff comparison: available to manager (inside their departments), owner and admin. A staff member sees only their own figure.
4. Filtered transaction list: period, type, category, dimension, currency, person, department, with CSV/XLSX export of exactly what the filters produced.

Reports are computed live from the transactions table. If a report ever becomes slow, the first move is a summary table refreshed on write, not a second database.

## Edit history

Every create, update, delete and restore appends a row to `transaction_revisions` holding the actor, the timestamp and a full snapshot of the transaction (including its dimension values). Deletes are soft. Staff may edit or delete their own records, admins may act on any; each such action is logged. Reports read live rows only, while the admin can inspect the full history of any transaction.

## Testing

- Scoping matrix: every role against list, summary, trend, export and single record access, including the attempt to widen scope through a client supplied `user_id` or `department_id`.
- `initData` validation: good signature, tampered payload, stale `auth_date`.
- Registration: open and closed toggle behaviour, pending user has no data access.
- Write validation: amount, currency, category compatibility, required dimensions.
- Idempotency: the same key twice produces one row.
- Revisions: created, updated, deleted and restored each append exactly one snapshot.
- Draft lifecycle: confirmation writes once, an expired draft cannot be confirmed.
- Department snapshot: moving a person between departments leaves past transactions where they were.

## Future compatibility (not built now)

Cashboxes stay out of the MVP, but the schema does not block them: an `accounts` table (owner, currency, opening balance) plus a nullable `transactions.account_id` is one migration with no data rewrite, the reporting layer treats "account" as one more group by key, and transfers between pockets arrive as a third value in the `type` enum paired with a transfer group id.

## Open questions

None blocking. Currency list, category tree and dimension set are data, seeded with the real Cara values before launch.
