# Design: Cara transactions Telegram mini app

Date: 2026-07-31
Status: approved design, not yet planned

## Goal

Cara staff open the mini app from Telegram to record an expense with more than an amount and a note, and to read the reports the bot cannot draw. Managers see their departments, the owner and admins see everything. It consumes the finished backend API and adds no server-side logic of its own.

## Scope (in)

- Telegram `initData` authentication against `POST /api/auth/telegram`, including the pending, blocked and registration-closed states.
- Entry: amount keypad, category chips, admin-defined dimensions, note, date, sticky defaults, idempotent save, undo.
- Reports: period totals with a category or dimension breakdown, trend over time, per-staff comparison for those allowed to see it.
- History: cursor-paginated filtered list, with edit and delete of a person's own records.
- Uzbek only, Telegram theme colours, light and dark.

## Scope (out)

- Any change to the backend. If a screen needs something the API does not expose, that is a separate card against the API, not a workaround in the client.
- Offline support, background sync, push notifications.
- Admin functions: activating users, editing categories or dimensions, settings. Those live in the Filament panel.
- CSV export (the panel and the API have it; a phone is the wrong place to download one).
- Russian and English. The product is single locale until someone decides otherwise.

## Constraints

- React 19, Vite, TypeScript, Tailwind, mirroring the sibling `cara-b2b-bot` stack but not its single-file structure.
- New dependencies: `recharts` (approved for charts), `vitest` and `@testing-library/react` (dev). Nothing else without approval.
- The client is a static build. It reaches the API over HTTPS and holds no secrets.
- Money is never summed across currencies. Every total, group row and axis belongs to one currency, exactly as the API returns it.
- Amount input accepts the same grammar as the bot (dot and comma as thousands separators, `k`, `ming`, `mln`, `mlrd` and their Cyrillic spellings), so staff do not learn two rules.

## Architecture

```
mini-app/
  src/
    api/        client.ts, types.ts          fetch wrapper, resource types
    auth/       useSession.ts                initData exchange, token, account states
    entry/      AmountKeypad, CategoryChips, DetailsSheet, useEntryForm
    reports/    SummaryView, TrendView, StaffView, PeriodPicker, usePeriod
    history/    TransactionList, TransactionSheet
    ui/         Tabs, Money, Sheet, EmptyState, ErrorState, Toast
    telegram/   webApp.ts                    theme params, MainButton, haptics
```

Three tabs: Add, Reports, History. Telegram's own back button closes the app; the tab bar owns navigation so no screen depends on the Telegram navigation API. Each tab keeps its own state, and the report period is shared across the report views through one store.

Data fetching is hand-rolled hooks over `client.ts`, not a query library: five endpoints, one bootstrap call per session, and no cache invalidation story worth the bundle. Revisit if it becomes painful.

## Authentication

On open the app posts `Telegram.WebApp.initData` to `/api/auth/telegram`.

- Active user: a bearer token comes back and is held **in memory only**. Nothing goes in `localStorage`; the app re-authenticates on every open, which is cheap, and a long-lived token in web storage on a shared phone is a liability with no upside.
- Pending user: the response carries `token: null` and a pending status. The app shows a waiting screen and nothing else.
- Blocked user or closed registration: 403, with the message the API returns.
- A 401 mid-session triggers exactly one silent re-authentication from the still-valid `initData` before an error is surfaced.

`GET /api/bootstrap` then supplies the category tree, dimensions with their values, currencies and the user's sticky defaults in one call.

## Entry

The amount keypad is focused on open and parses with the bot's grammar. Category chips sit below it in the tree's own `sort` order, with the sticky default first. Usage-based ordering would need an endpoint the API does not have, and inventing one here is out of scope; if the chips prove hard to scan once the real category tree lands, that is a card against the API. Dimensions collapse under a "Details" section prefilled from sticky defaults; any dimension the API marks required renders expanded and blocks save until answered. The date defaults to today.

Telegram's MainButton is the save action, so the primary control is where the platform puts it. Save posts to `POST /api/transactions` with a client-generated UUID in `Idempotency-Key`, so a double tap on a poor connection cannot write twice. Success gives a haptic tick, resets the form to defaults, and shows a toast offering Undo, which calls `DELETE /api/transactions/{id}` while the toast is up.

Validation errors from the API render against their fields. The client does not re-implement the domain rules; the server is the authority, and its 422 body carries the field keys.

## Reports

One period picker (this month, last month, custom range) shared by every report view.

- **Summary** — income and expense totals for the period, then a Recharts donut broken down by category, with a switch to group by any active dimension instead. Each currency renders as its own section with its own total and its own chart.
- **Trend** — Recharts bars over day, week or month, one series per currency and type.
- **Staff comparison** — a ranked bar list, rendered only when the caller's permissions allow it. A staff member never sees this tab.

Empty periods say so in words rather than drawing an empty axis.

## History and editing

A cursor-paginated list with infinite scroll and filters for period, category, dimension and currency. Tapping a row opens a sheet showing the full record and its revision count. A person's own records offer edit and delete; the API's 403 or 404 is the authority on whether an action is allowed, and the client's permission guess only decides what to render optimistically. Delete asks once and is soft server-side.

## Errors and edge cases

- A failed request shows what failed and offers retry; it never fails silently.
- 429 from the API surfaces as "too many requests, try again in a moment", since the authenticated group and the export both carry ceilings.
- An amount the parser refuses shows the same hint style the bot uses, not a silent correction.
- A category or dimension value deactivated since the bootstrap call causes a 422 on save; the client refetches the bootstrap payload and asks the user to pick again.

## Testing

Vitest with React Testing Library.

- Unit: the amount grammar against the same table the bot's parser uses, the money formatter (minor units to display, per currency exponent), and the filter serialiser.
- Component: the required-dimension gate blocking save, the idempotency key surviving a retry, per-currency sections rendering separately, and the pending-user screen showing nothing but the wait.
- No end-to-end suite. The backend has one, and duplicating it here buys nothing.

## Open questions

None blocking. The Uzbek strings in this client are subject to the same native-speaker review the bot's strings need.
