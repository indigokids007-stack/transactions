---
status: in-review
intent_source: "Brainstormed 2026-07-31: Telegram mini app against the finished backend API. Human chose entry plus all four reports, Recharts, bottom tab bar."
created: 2026-07-31
role_history: [planner: claude-opus-5, builder: claude-code-subagents, reviewer: gpt-5.6-sol (round 1), gpt-5.6-terra (round 2, seat downgraded)]
lane: full
review_rounds_used: 2
---

## Task: Cara transactions Telegram mini app

### Goal
Cara staff open the mini app from Telegram to record an expense with dimensions and to read the reports the bot cannot draw. Managers see their departments, the owner and admins see everything. It consumes the finished backend API and adds no server-side logic.

### Acceptance criteria
1. **The human's ask, verbatim:** "Go with subagent-driven, I require you to use react-best-practices skill" — nine planned tasks built through subagent-driven development, with the react-best-practices skill enabled and its rules applied to every task.
2. Telegram `initData` authentication, including pending, blocked and registration-closed states.
3. Entry: amount keypad using the bot's grammar, category chips, dimensions, sticky defaults, idempotent save, undo.
4. Reports: period totals with category or dimension breakdown, trend over time, per-staff comparison gated on permissions.
5. History: cursor-paginated filtered list with edit and delete of a person's own records.
6. Every screen reachable from the tab bar. Money never summed across currencies.
7. `npm test`, `npm run lint`, `npm run build` clean.

### Context for reviewer

**Mitigations already in place, do not raise again:**
- The client's amount grammar was verified against the real backend parser across 716 and 1,269 differential inputs in two separate rounds. Zero unsafe divergences; every divergence is toward refusal and comes from note-splitting the client does not do.
- The whitespace class is pinned by a test asserting a hardcoded 26-code-point contract, because a comment about it was wrong twice.
- The bearer token is held in a closure, never in `localStorage`, `sessionStorage` or a cookie. Grep-confirmed.
- Per-currency separation is proven by negative assertions in both directions in the summary, trend and staff views, each mutation-verified.
- Fetch effects use ignore-flag cleanup, each with a race test that resolves the OLDER request LAST.
- All three tab panels stay mounted with `hidden`, so each tab keeps its state; `EntryScreen` takes an `active` prop because Telegram's MainButton is chrome outside the DOM.

**Deliberate tradeoffs, with reasons:**
- Amount is not editable in the history sheet. Re-parsing a stored decimal through a grammar where `.` means thousands could silently change the value. Human ruled 2026-07-31: leave it, delete-and-recreate works, revision log records both. Follow-up card.
- No query library. Five endpoints and one bootstrap call per session did not justify the bundle.
- The client never re-implements a domain rule to pre-empt a 4xx. The server is the authority.

**Out of scope:**
- Russian and English strings. Uzbek only, and those strings are agent drafts awaiting a native speaker.
- Hosting, TLS, CORS and Sanctum stateful domains. Tracked in `tasks/T2-transactions-followups.md`.
- Amount editing (above). CSV export on a phone.

**Known gap the reviewer should weigh:**
- The project has no ESLint. `npm run lint` is `tsc -b`, a typecheck only. Every task was told never to suppress `react-hooks/exhaustive-deps`, and that rule was honoured by discipline, not tooling. Adding ESLint is a dependency and config change needing human approval.

### Notes from previous role
Builder: nine tasks, 208 tests. Three plan defects were found by implementers and fixed: a test assertion that could never match because testing-library normalises non-breaking spaces; types that assumed Laravel wraps bootstrap resources in `data` when it does not; and no task wiring `ReportsScreen` into `App.tsx`, which left the trend and staff views unreachable.
