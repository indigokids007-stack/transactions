---
status: in-progress
intent_source: "Brainstormed 2026-07-26: Cara staff expense and income ledger, Telegram mini app plus bot, reports, custom dimensions."
created: 2026-07-26
role_history: [planner: claude-opus-5, builder: claude-code-subagents, reviewer: pending]
labels: [high-risk]
---

## Task: Transactions backend (Cara staff ledger)

### Goal
Cara staff register their own expenses and incomes from a Telegram bot or mini app and read reports of their own spend. Managers see their department, owner and admins see everything. This card covers the Laravel backend only.

### Scope (in)
- Everything in `docs/superpowers/plans/2026-07-26-transactions-backend.md`, Tasks 1 to 13.

### Scope (out)
- React mini app (separate card and plan).
- Cashboxes/accounts, receipt photos, approvals, currency conversion.
- XLSX export (needs a Composer dependency, awaiting human approval; CSV ships).

### Constraints
- See the Global Constraints section of the plan.
- Docker only; the host has no PHP or Composer.

### Acceptance criteria
- All 13 plan tasks committed on `feat/transactions-backend`.
- `make test`, `make analyze`, `make pint` clean.
- End-to-end test passes: bot entry recorded after confirmation and visible in the author's report, invisible to unrelated staff.

### Design spec
`docs/superpowers/specs/2026-07-26-transactions-design.md`

### Notes from previous role
Planner: high-risk (Telegram auth plus personal spend data). Review pipeline is the cross-family Codex review, the security specialist on the auth and scoping tasks, plus one complementary flagship adversarial pass.
