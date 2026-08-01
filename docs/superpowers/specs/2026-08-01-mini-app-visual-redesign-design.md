# Design: Mini app visual redesign

## Purpose

The Telegram mini app (Add / Reports / History) works but reads as unstyled: flat borderless
cards, a monochrome pie chart with no per-slice color, and a plain text bottom tab bar. This
redesign gives the app a polished mobile-app look (reference: a Monex fintech UI kit sent by the
user), fixes the pie chart's missing per-category colors, and replaces the text tab bar with an
icon-based mobile nav with a raised center "Add" button. Scope is presentation only — no
behavior, API, or state changes.

## Constraints

- App theme is driven live by Telegram's `themeParams` (`useTheme.ts` writes `--tg-bg`,
  `--tg-text`, `--tg-hint`, `--tg-button`, `--tg-button-text`, `--tg-secondary-bg` as CSS custom
  properties). This must keep working — background/text/hint stay Telegram-driven so Telegram's
  own light/dark switch still works.
- No existing icon library. `lucide-react` is a new dependency — approved by the user for this
  task specifically (small, tree-shakeable, no runtime cost beyond the icons actually imported).
- Chart colors must follow the `dataviz` skill's method: color assigned last, categorical hues in
  a fixed validated order, never cycled or auto-generated.
- All existing `role="tablist"`/`role="tab"`/`aria-selected`/`aria-controls` semantics in
  `Tabs.tsx` and the `hidden`-panel structure in `App.tsx` are behavior, not presentation — must
  be preserved unchanged. Same for `data-testid` hooks used by existing tests
  (`currency-${currency}`, `trend-${currency}`, `staff-${currency}`, `staff-row`).

## Color tokens (fixed accent overlay)

Add to `mini-app/src/styles.css`, alongside the existing `--tg-*` fallback block:

```css
:root {
  --accent: #2a78d6;
  --accent-2: #eb6834;
  --surface-card: #fcfcfb;
  --border: rgba(11, 11, 11, 0.1);
}
@media (prefers-color-scheme: dark) {
  :root {
    --accent: #3987e5;
    --accent-2: #d95926;
    --surface-card: #1a1a19;
    --border: rgba(255, 255, 255, 0.1);
  }
}
```

`--tg-bg` / `--tg-text` / `--tg-hint` / `--tg-secondary-bg` are untouched — still written by
`useTheme.ts` from Telegram's own theme. `--accent` is the new fixed brand color and replaces
`var(--tg-button)` wherever it currently marks the primary/selected action: active tab, chip
selected state, save button, chart series 1. `--accent-2` is used only where a second fixed
categorical color is needed (expense bar series). This app has no user-facing light/dark toggle,
so `prefers-color-scheme` alone (no `data-theme` scope) is enough here — unlike a themeable
product, there's no in-app toggle to also satisfy.

Full 8-slot categorical table (pie chart, `references/palette.md` from the `dataviz` skill,
already validated — not re-validated here since it's the skill's stock reference palette):

| Slot | Light | Dark |
|---|---|---|
| 1 | `#2a78d6` | `#3987e5` |
| 2 | `#eb6834` | `#d95926` |
| 3 | `#1baf7a` | `#199e70` |
| 4 | `#eda100` | `#c98500` |
| 5 | `#e87ba4` | `#d55181` |
| 6 | `#008300` | `#008300` |
| 7 | `#4a3aa7` | `#9085e9` |
| 8 | `#e34948` | `#e66767` |

## Bottom nav — icon-based mobile style with center FAB

`mini-app/src/ui/Tabs.tsx`: presentation-only rewrite, same props (`TabId`, `TabItem`, `value`,
`onChange`, `items`) and same `role="tablist"`/`role="tab"`/`aria-selected`/`aria-controls`/
`tabIndex` contract — `App.tsx` does not change.

- Bar: `background: var(--surface-card)`, top border `var(--border)`, adds
  `padding-bottom: env(safe-area-inset-bottom)` for the home-indicator area on notched phones.
- Reports tab (left): `lucide-react` `PieChart` icon above a small label. Icon+label color
  `var(--tg-hint)` unselected, `var(--accent)` selected.
- History tab (right): `lucide-react` `History` icon above a small label, same color rule.
- Add tab (center): rendered differently from the other two — a raised circular button (48px),
  `background: var(--accent)`, white `lucide-react` `Plus` icon, no label, `transform: translateY(-16px)`
  so it overlaps the bar's top edge, `box-shadow` for elevation. Still the same `<button role="tab">`
  element (same click handler, same `aria-selected`), only its style branches on `item.id === 'add'`.

Rationale for singling out "Add": `EntryScreen` already binds Telegram's `MainButton` specifically
to save, i.e. the product already treats Add as the primary action inside Telegram — the FAB
makes that same hierarchy visible in the tab bar itself, and matches the Monex reference image.

## Chart colors

`mini-app/src/reports/CurrencySection.tsx` (pie, `SummaryView`'s per-currency breakdown):

- Add `<Cell>` per slice inside `<Pie>`, `fill` indexed into the 8-slot categorical table above,
  in the order `groups` already arrives in (backend-determined, unchanged).
- 9th+ group: fold into one synthetic "Other" slice (sum of the remaining groups' `amount_minor`,
  concatenation of their labels or a fixed "Other" label) before render, so the chart never
  exceeds the validated 8-slot order. New helper in the same file, pure function over `groups`.
- Existing `<Legend>` stays (≥2 series always keeps a legend per the dataviz method). Add a direct
  percentage label on slices when the bucket has ≤4 groups (post-Other-folding count) — beyond
  that, legend-only, matching the skill's "≤4 also direct-labeled" rule.

`mini-app/src/reports/TrendSection.tsx` (bar, `TrendView`): two fixed series, not a dynamic
category list — `income` bar `fill: var(--accent)` (slot 1), `expense` bar `fill: var(--accent-2)`
(slot 2), replacing the current `var(--tg-button)` / `var(--tg-hint)`. `<Legend>` unchanged.

`mini-app/src/reports/StaffSection.tsx`: plain ranked list, no chart, no color change — card
polish only (see next section).

## Screen / card polish (Add, Reports, History)

Applied as a consistent token swap across existing components — no new components, no logic
changes:

- Section cards (`CurrencySection`, `TrendSection`, `StaffSection`, the filter groups in
  `Filters.tsx`/`PeriodPicker.tsx`): `border`-only styling gets `background: var(--surface-card)`,
  `border-color: var(--border)`, corner radius bumped `rounded-lg` → `rounded-xl`, plus a
  `shadow-sm`.
- Chip/button selected states (`PeriodPicker`, `CategoryChips`, `ReportsScreen`'s view switch):
  selected background changes from `var(--tg-button)` to `var(--accent)`.
- `EntryScreen`'s save button (the non-Telegram fallback button): background `var(--accent)`.
- Spacing normalized: card padding `p-3` → `p-4`, gap between stacked cards `gap-2` → `gap-3`,
  consistent `px-4` page gutter across all three screens (currently inconsistent — some screens
  already use it, some don't).
- Icons added where rows are currently text-only: `TransactionList` entry rows get a small
  `lucide-react` icon (e.g. `ArrowDownLeft/ArrowUpRight` for income/expense, or a category icon if
  one is already modeled — falls back to the arrow icons otherwise).
- `EmptyState`, `ErrorState`, `Toast`: no structural change, re-skinned to the same
  `--surface-card`/`--accent` tokens for visual consistency with everything else.

## Out of scope

- No onboarding/splash screens (the Monex reference's auth flow doesn't apply — Telegram handles
  auth, `useSession` already covers it).
- No animation or gesture work beyond what already exists (`Sheet.tsx`, `Toast.tsx`).
- No new screens, no new API calls, no change to `usePeriod`, `useReportFetch`, `useEntryForm`,
  `useTransactions`, or any other hook's behavior.
- No change to `data-testid` values or ARIA roles/attributes already relied on by existing tests.
- No dark-mode toggle inside the app — dark mode continues to follow Telegram's own theme signal
  only.

## Testing

Existing tests assert on `role`, `aria-*`, and `data-testid`, not on class names or inline
styles — the polish pass (colors, spacing, `Cell`/icon additions) should not need test changes.
New behavior introduced by this design (the pie chart's "Other" folding helper) gets a unit test
for that helper's grouping logic. No other new logic is introduced, so no other new tests are
required.
