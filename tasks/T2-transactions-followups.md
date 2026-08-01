---
status: intent
intent_source: "Deferred findings from the 13-task backend build and its whole-branch review, 2026-07-26 to 2026-07-31."
created: 2026-07-31
role_history: [planner: claude-opus-5]
---

## Task: Transactions backend follow-ups

### Goal
Everything the backend build deliberately deferred, in priority order, so none of it is lost. Each block is a candidate card, not a single task.

### Before this runs in production

- **Uzbek strings are unreviewed drafts.** `lang/uz/bot.php` and the panel's `delete_refused.*` are the entire user-facing surface and were written by an agent, not a speaker. Get them read before staff see them.
- **Real reference data.** The seeder ships placeholders (Sales/Warehouse/Office, Transport/Ofis/Marketing/Boshqa, a `branch` dimension). Replace with Cara's actual departments, categories and dimensions.
- **Capture one real `initData` fixture** from the production bot and assert it validates. Every auth test builds its own payload with the same helper the implementation uses, so a shared misunderstanding would pass.
- **Production deployment work, none of it built:** a production compose file (`APP_ENV=production`, DB port unpublished, real password), TLS or a reverse proxy (Telegram will not deliver to plain http), `trustProxies` and `SESSION_SECURE_COOKIE` for the panel login, logs to stdout, and CI running `make test`, `make analyze`, `make pint`.
- **Decide on `migrate --force` at container start.** Idempotent and fine for dev; applies pending migrations with no human in the loop on a server.
- **`make fresh` carries `--force`**, so it drops every table without the production confirmation prompt. Consider removing `--force` from `fresh` while keeping it on `migrate`.

### Product gaps against the spec

- **Admin notification on a new pending user.** The spec says admins are notified; nothing exists, so admins must poll the Users list.
- **Bot edit and delete after saving.** The spec's confirmation offers both; the confirm message carries no keyboard and no edit/delete callback verb exists.
- **Panel reporting for the owner.** Reports are API-only, so the owner's actual surface has no reports and no export action.
- **Locale change endpoint.** Locale is set once at creation with no way to change it; `lang/ru` and `lang/en` do not exist while `ru`/`en` remain accepted values.
- **Currencies are config, not an admin setting.**
- **Preview omits chosen dimension values**, and the category keyboard shows up to 30 in sort order rather than the most used, with no paging. Will bite as soon as the real category tree lands.
- **Admin-creates-on-behalf-of-staff** has no path, so a staff member who does not use Telegram cannot have expenses recorded for them.

### Parser (bot amount grammar)

- **A case-fold-equivalent but table-absent suffix silently drops the magnitude.** `7тыᲃ` (U+1C83, the Cyrillic "long es") matches the `тыс` alternation caselessly in PCRE, misses the table lookup, and records 7 instead of 7000. Found during the mini app's differential port; the client mirrors the behaviour exactly, so it is a backend grammar hazard rather than a divergence. Same class as the Cyrillic `к` homoglyph fixed during the build: the alternation matches more spellings than the lookup table knows.
- Latin transliterations missing from the magnitude table (`5 lyam` records 5).
- Mixed-script word magnitudes (`5 мln`, `5 mлн`) record a fraction; needs a mid-word keyboard switch, unlikely but silent.
- Deliberate refusals worth watching for complaints: `50 k non` style spaced suffixes, `1 mln 500 ming`, notes opening with 2+ digits (`50000 12 kishi`), `12.50` meaning 12500.

### Robustness and polish

- Malformed pagination cursor returns 500 rather than 422.
- `withTrashed` idempotency hit returns a soft-deleted transaction as a 200 with no `deleted_at` in the resource.
- `occurred_on` accepts relative date strings and has no lower bound.
- Revision snapshots store bare dimension value ids, so a renamed or removed value cannot be reconstructed from the audit trail.
- Groups and revisions endpoints are unpaginated; `categoryBranch` reads the whole categories table per filtered request.
- Numerically-keyed dimension filter (`dimension[123]`) is silently dropped.
- Export orders by `id asc` while the list orders `occurred_on desc`; the panel's category filter does not expand descendants the way the API does.
- Token eviction is by insertion order rather than `last_used_at`; the cap is per user, not per device; a future-dated `auth_date` is accepted.
- Panel: unconstrained role select at activation, `/admin/transactions/create` returns 500 rather than 404, ~12 authored labels still in English, `CategoryResource` deletes meet a raw FK error rather than the guard's sentence.
- Telegram client retries non-2xx as well as connection failures, so a routine 400 costs three requests; worst case on the confirm path is ~61s against Telegram's ~60s webhook tolerance, and there is no `update_id` dedupe.
- Panel guard tests cover only the Department bulk and edit-header paths; the Dimension and DimensionValue equivalents are wired but untested.
- `/api/health` is unauthenticated, unthrottled, and issues a DB query per request.
