# Mini App Visual Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the Telegram mini app (Add / Reports / History) a polished mobile-app look: colored pie/bar chart series instead of one flat gray, a mobile-style bottom nav with a raised center "Add" button, and consistent card/spacing polish across all three screens.

**Architecture:** Presentation-only changes on top of the existing React + Tailwind + Recharts app. A new fixed accent color layer (CSS custom properties, `prefers-color-scheme`-driven) sits alongside the existing Telegram-theme-driven `--tg-*` variables — background/text/hint stay Telegram-driven, brand accents and chart colors are fixed. No API, hook, or state changes; existing `role`/`aria-*`/`data-testid` contracts are preserved exactly.

**Tech Stack:** React 19, TypeScript, Tailwind CSS, Recharts 3, Vitest + Testing Library. New dependency: `lucide-react` (icons for the bottom nav and transaction rows).

## Global Constraints

- `--tg-bg` / `--tg-text` / `--tg-hint` / `--tg-secondary-bg` stay written by `useTheme.ts` from Telegram's `themeParams` — never hardcoded, never replaced.
- Chart categorical colors use the `dataviz` skill's validated 8-slot table verbatim (see Task 2) — fixed order, never cycled, 9th+ category folds into "Other".
- No change to any `role`, `aria-*` attribute, or `data-testid` value already asserted on by an existing test (`Tabs.test.tsx`, `SummaryView.test.tsx`, `TrendView.test.tsx`, `ReportsScreen.test.tsx`, and any other existing `*.test.tsx` under `mini-app/src`).
- No new screens, no new API calls, no change to `usePeriod`, `useReportFetch`, `useEntryForm`, `useTransactions`.
- `npm run lint` (`tsc -b --pretty false`) and `npm run test` (`vitest run`) must pass after every task, run from `mini-app/`.

---

### Task 1: Add `lucide-react` and the fixed accent/chart color tokens

**Files:**
- Modify: `mini-app/package.json`, `mini-app/package-lock.json` (via `npm install`, not hand-edited)
- Modify: `mini-app/src/styles.css`

**Interfaces:**
- Produces: CSS custom properties `--accent`, `--accent-2`, `--surface-card`, `--border`, `--chart-1` through `--chart-8` — consumed by every later task in this plan.

- [ ] **Step 1: Install the dependency**

```bash
cd mini-app && npm install lucide-react@^1.28.0
```

- [ ] **Step 2: Verify it installed cleanly**

Run: `cd mini-app && npm ls lucide-react`
Expected: prints `lucide-react@1.28.0` (or the closest `^1.28.0` resolves to) with no `UNMET DEPENDENCY` error.

- [ ] **Step 3: Add the color tokens**

Edit `mini-app/src/styles.css` — insert after the existing `:root { --tg-... }` block (after line 16, before the `body { ... }` rule):

```css
:root {
  /* Fixed brand accent + chart colors, independent of Telegram's theme — see
     docs/superpowers/specs/2026-08-01-mini-app-visual-redesign-design.md. The dataviz
     skill's validated 8-slot categorical order (references/palette.md), fixed, never
     cycled. --accent/--accent-2 alias slots 1/2 rather than duplicating their hex. */
  --chart-1: #2a78d6;
  --chart-2: #eb6834;
  --chart-3: #1baf7a;
  --chart-4: #eda100;
  --chart-5: #e87ba4;
  --chart-6: #008300;
  --chart-7: #4a3aa7;
  --chart-8: #e34948;
  --accent: var(--chart-1);
  --accent-2: var(--chart-2);
  --surface-card: #fcfcfb;
  --border: rgba(11, 11, 11, 0.1);
}

@media (prefers-color-scheme: dark) {
  :root {
    --chart-1: #3987e5;
    --chart-2: #d95926;
    --chart-3: #199e70;
    --chart-4: #c98500;
    --chart-5: #d55181;
    --chart-6: #008300;
    --chart-7: #9085e9;
    --chart-8: #e66767;
    --surface-card: #1a1a19;
    --border: rgba(255, 255, 255, 0.1);
  }
}
```

- [ ] **Step 4: Run the full test suite to confirm nothing broke**

Run: `cd mini-app && npm run test`
Expected: PASS, same test count as before this task (a pure CSS addition changes no component behavior).

- [ ] **Step 5: Commit**

```bash
git add mini-app/package.json mini-app/package-lock.json mini-app/src/styles.css
git commit -m "feat: add lucide-react and fixed accent/chart color tokens"
```

---

### Task 2: Chart color slots + "Other" folding in `CurrencySection`

**Files:**
- Modify: `mini-app/src/reports/CurrencySection.tsx`
- Create: `mini-app/src/reports/CurrencySection.test.tsx`

**Interfaces:**
- Consumes: `GroupRow` (already exported from this file: `AggregateRow & { key: string | null; label: string }`), `strings.reports` (add `other` in Task 2b below).
- Produces: `CHART_COLORS: string[]` (8 entries, `'var(--chart-1)'` … `'var(--chart-8)'`), `foldGroupsToSlots(groups: GroupRow[], otherLabel: string): GroupRow[]` — both exported from `CurrencySection.tsx` for the test and for reuse if a later task needs them.

- [ ] **Step 1: Add the `other` string**

Edit `mini-app/src/strings.ts`, inside the `reports` object, after the `byStaff: 'Xodimlar',` line (line 85):

```typescript
    byStaff: 'Xodimlar',
    // The pie chart's 9th+ category, folded into one slice so the chart never exceeds
    // the validated 8-slot color order (dataviz skill).
    other: 'Boshqa',
```

- [ ] **Step 2: Write the failing test for `foldGroupsToSlots`**

Create `mini-app/src/reports/CurrencySection.test.tsx`:

```tsx
import { describe, expect, it } from 'vitest'
import { foldGroupsToSlots } from './CurrencySection'
import type { GroupRow } from './CurrencySection'

function row(label: string, amount_minor: number): GroupRow {
  return {
    key: label,
    label,
    currency: 'UZS',
    type: 'expense',
    amount_minor,
    amount: String(amount_minor),
    count: 1,
  }
}

describe('foldGroupsToSlots', () => {
  it('returns groups unchanged when there are 8 or fewer', () => {
    const groups = [row('A', 100), row('B', 200)]
    expect(foldGroupsToSlots(groups, 'Boshqa')).toEqual(groups)
  })

  it('returns an empty array for an empty input', () => {
    expect(foldGroupsToSlots([], 'Boshqa')).toEqual([])
  })

  it('keeps exactly 8 groups unchanged when there are exactly 8', () => {
    const groups = Array.from({ length: 8 }, (_, i) => row(`G${i}`, i + 1))
    expect(foldGroupsToSlots(groups, 'Boshqa')).toEqual(groups)
  })

  it('folds the 9th+ group into one "Other" slice, keeping the first 7 untouched', () => {
    const groups = [
      row('A', 500),
      row('B', 400),
      row('C', 300),
      row('D', 200),
      row('E', 150),
      row('F', 120),
      row('G', 100),
      row('H', 80),
      row('I', 20),
    ]

    const result = foldGroupsToSlots(groups, 'Boshqa')

    expect(result).toHaveLength(8)
    expect(result.slice(0, 7)).toEqual(groups.slice(0, 7))
    expect(result[7].label).toBe('Boshqa')
    // H (80) + I (20) folded together.
    expect(result[7].amount_minor).toBe(100)
    expect(result[7].count).toBe(2)
    expect(result[7].currency).toBe('UZS')
  })
})
```

- [ ] **Step 3: Run it to verify it fails**

Run: `cd mini-app && npx vitest run src/reports/CurrencySection.test.tsx`
Expected: FAIL — `foldGroupsToSlots` is not exported from `./CurrencySection`.

- [ ] **Step 4: Implement `CHART_COLORS` and `foldGroupsToSlots`, wire `Cell`s and the "Other" fold into the pie**

Replace the full contents of `mini-app/src/reports/CurrencySection.tsx` with:

```tsx
import { Cell, Legend, Pie, PieChart, ResponsiveContainer } from 'recharts'
import { MoneyAmount } from '../ui/Money'
import { strings } from '../strings'
import type { AggregateRow } from '../api/types'

export type GroupRow = AggregateRow & { key: string | null; label: string }

export type CurrencySectionProps = {
  currency: string
  totals: AggregateRow[]
  groups: GroupRow[]
  /**
   * Fixed pixel dimensions for the chart. Recharts' `ResponsiveContainer` measures its
   * parent via `ResizeObserver`, which jsdom never fires, so a percentage width renders
   * nothing under test. Passing explicit numbers here short-circuits that measurement
   * (recharts renders immediately from fixed props); the app leaves both undefined and
   * gets the responsive, percentage-based container instead.
   */
  chartWidth?: number
  chartHeight?: number
}

// The dataviz skill's validated 8-slot categorical order (references/palette.md),
// fixed — never cycled, never generated. Referenced as CSS custom properties (defined
// in styles.css, light/dark pair per slot) rather than raw hex, so dark mode swaps in
// one place.
export const CHART_COLORS = [
  'var(--chart-1)',
  'var(--chart-2)',
  'var(--chart-3)',
  'var(--chart-4)',
  'var(--chart-5)',
  'var(--chart-6)',
  'var(--chart-7)',
  'var(--chart-8)',
]

const MAX_CHART_SLOTS = CHART_COLORS.length

// Caps a currency's groups at the validated 8-slot color order: the first 7 pass
// through untouched, everything from the 8th group on folds into one synthetic "Other"
// slice. `amount_minor` and `count` sum in plain number arithmetic — the same
// precision the pie already accepted for `dataKey="amount_minor"` before this change,
// since nothing here displays the folded slice's currency amount as text (only its
// label, in the legend, and its share of the pie). Never mutates `groups`.
export function foldGroupsToSlots(groups: GroupRow[], otherLabel: string): GroupRow[] {
  if (groups.length <= MAX_CHART_SLOTS) return groups

  const kept = groups.slice(0, MAX_CHART_SLOTS - 1)
  const folded = groups.slice(MAX_CHART_SLOTS - 1)

  const other: GroupRow = {
    key: 'other',
    label: otherLabel,
    currency: folded[0].currency,
    type: folded[0].type,
    amount_minor: folded.reduce((sum, row) => sum + row.amount_minor, 0),
    amount: String(folded.reduce((sum, row) => sum + row.amount_minor, 0)),
    count: folded.reduce((sum, row) => sum + row.count, 0),
  }

  return [...kept, other]
}

type PercentLabelProps = { percent?: number }

function percentLabel({ percent }: PercentLabelProps): string {
  return percent === undefined ? '' : `${Math.round(percent * 100)}%`
}

// One currency's slice of a summary report: its own totals, its own pie chart. Never
// receives another currency's rows, and never combines with one — the caller
// (`SummaryView`) is the only place currencies are split apart, and this component has
// no way to add two of them back together.
export function CurrencySection({ currency, totals, groups, chartWidth, chartHeight }: CurrencySectionProps) {
  const slots = foldGroupsToSlots(groups, strings.reports.other)

  return (
    <section
      data-testid={`currency-${currency}`}
      className="rounded-xl border p-4 shadow-sm"
      style={{ borderColor: 'var(--border)', background: 'var(--surface-card)' }}
    >
      <h3 className="text-sm font-semibold">{currency}</h3>

      <dl className="mt-2 flex gap-6 text-sm">
        {totals.map((row) => (
          <div key={row.type}>
            <dt className="opacity-70">{strings.entry[row.type]}</dt>
            <dd>
              {/* `row.amount` is the backend's precision-safe decimal string; `row.amount_minor`
                  is the same figure as a JSON number, which loses precision above 2^53 once
                  enough transactions are summed into one aggregate row. See `MoneyAmount`. */}
              <MoneyAmount amount={row.amount} currency={row.currency} />
            </dd>
          </div>
        ))}
      </dl>

      <div className="mt-2">
        <ResponsiveContainer width={chartWidth ?? '100%'} height={chartHeight ?? 220}>
          <PieChart>
            <Pie
              data={slots}
              dataKey="amount_minor"
              nameKey="label"
              isAnimationActive={false}
              label={slots.length <= 4 ? percentLabel : false}
            >
              {slots.map((slot, index) => (
                <Cell key={slot.key ?? slot.label} fill={CHART_COLORS[index % CHART_COLORS.length]} />
              ))}
            </Pie>
            <Legend />
          </PieChart>
        </ResponsiveContainer>
      </div>
    </section>
  )
}
```

- [ ] **Step 5: Run the new test to verify it passes**

Run: `cd mini-app && npx vitest run src/reports/CurrencySection.test.tsx`
Expected: PASS, all 4 cases.

- [ ] **Step 6: Run the full suite (including `SummaryView.test.tsx`, which exercises `CurrencySection` through the screen)**

Run: `cd mini-app && npm run test`
Expected: PASS, no regressions — `SummaryView.test.tsx`'s legend-text assertions (`findByText('Taksi')`, etc.) still pass since `nameKey`/labels are unchanged, only `fill` and the new `Cell`/`Other`-folding were added.

- [ ] **Step 7: Typecheck**

Run: `cd mini-app && npm run lint`
Expected: PASS, no type errors.

- [ ] **Step 8: Commit**

```bash
git add mini-app/src/strings.ts mini-app/src/reports/CurrencySection.tsx mini-app/src/reports/CurrencySection.test.tsx
git commit -m "feat: color the summary pie chart per category, fold 9th+ into Other"
```

---

### Task 3: Fixed accent colors on the trend bar chart

**Files:**
- Modify: `mini-app/src/reports/TrendSection.tsx`

**Interfaces:**
- Consumes: `--accent`, `--accent-2` (Task 1), `--surface-card`, `--border` (Task 1).
- No change to `TrendSection`'s exported types (`TrendPoint`, `TrendSectionProps`) or `data-testid` (`trend-${currency}`).

- [ ] **Step 1: Swap the bar fills and card surface**

Edit `mini-app/src/reports/TrendSection.tsx`:

Replace:
```tsx
    <section
      data-testid={`trend-${currency}`}
      className="rounded-lg border p-3"
      style={{ borderColor: 'var(--tg-hint)' }}
    >
```
with:
```tsx
    <section
      data-testid={`trend-${currency}`}
      className="rounded-xl border p-4 shadow-sm"
      style={{ borderColor: 'var(--border)', background: 'var(--surface-card)' }}
    >
```

Replace:
```tsx
            <Bar dataKey="income" name={strings.entry.income} fill="var(--tg-button)" isAnimationActive={false}>
              <LabelList position="top" valueAccessor={labelValue('incomeAmount', currency)} />
            </Bar>
            <Bar dataKey="expense" name={strings.entry.expense} fill="var(--tg-hint)" isAnimationActive={false}>
              <LabelList position="top" valueAccessor={labelValue('expenseAmount', currency)} />
            </Bar>
```
with:
```tsx
            <Bar dataKey="income" name={strings.entry.income} fill="var(--accent)" isAnimationActive={false}>
              <LabelList position="top" valueAccessor={labelValue('incomeAmount', currency)} />
            </Bar>
            <Bar dataKey="expense" name={strings.entry.expense} fill="var(--accent-2)" isAnimationActive={false}>
              <LabelList position="top" valueAccessor={labelValue('expenseAmount', currency)} />
            </Bar>
```

- [ ] **Step 2: Run `TrendView.test.tsx` to confirm no regression**

Run: `cd mini-app && npx vitest run src/reports/TrendView.test.tsx`
Expected: PASS — no test asserts on `fill` or card class names, only on bar label text and axis ticks.

- [ ] **Step 3: Commit**

```bash
git add mini-app/src/reports/TrendSection.tsx
git commit -m "feat: give the trend chart's income/expense bars fixed accent colors"
```

---

### Task 4: Icon-based bottom nav with a raised center "Add" button

**Files:**
- Modify: `mini-app/src/ui/Tabs.tsx`

**Interfaces:**
- Consumes: `lucide-react`'s `PieChart`, `History`, `Plus` icons (Task 1's dependency).
- No change to `TabId`, `TabItem`, `TabsProps`, or the `role`/`aria-*` contract — `App.tsx` needs no changes.

- [ ] **Step 1: Write a test pinning the new center-button structure, alongside the existing a11y tests**

Edit `mini-app/src/ui/Tabs.test.tsx` — append after the existing three `it(...)` blocks:

```tsx
it('renders the add tab as a raised button with no visible text label', () => {
  render(<Tabs value="reports" onChange={() => {}} items={items} />)

  const addTab = screen.getByRole('tab', { name: 'Kiritish' })
  // The FAB carries its label via `aria-label` for a11y — its accessible name still
  // matches `items`' label — but shows no separate text node beside the icon, unlike
  // the Reports/History tabs which render their label as visible text.
  expect(addTab).toHaveAttribute('aria-label', 'Kiritish')
  expect(addTab.textContent?.trim()).toBe('')
})
```

- [ ] **Step 2: Run it to verify it fails**

Run: `cd mini-app && npx vitest run src/ui/Tabs.test.tsx`
Expected: FAIL — current `Tabs` renders the label as visible text for every tab, so `textContent` is `'Kiritish'`, not empty, and there is no `aria-label`.

- [ ] **Step 3: Implement the new `Tabs`**

Replace the full contents of `mini-app/src/ui/Tabs.tsx`:

```tsx
import { History, PieChart, Plus } from 'lucide-react'

export type TabId = 'add' | 'reports' | 'history'

export type TabItem = {
  id: TabId
  label: string
}

type TabsProps = {
  value: TabId
  onChange: (id: TabId) => void
  items: TabItem[]
}

const ICONS: Record<TabId, typeof PieChart> = {
  add: Plus,
  reports: PieChart,
  history: History,
}

// A controlled tab bar: the parent owns `value` and receives the requested change
// through `onChange`. `role="tablist"`/`role="tab"` plus `aria-selected` and
// `aria-controls` let callers (and tests) find these by role instead of by class name —
// unchanged by this component's visual redesign (icon-based, center "Add" FAB).
export function Tabs({ value, onChange, items }: TabsProps) {
  return (
    <div
      role="tablist"
      className="flex items-end justify-around border-t px-2 pt-2"
      style={{
        borderColor: 'var(--border)',
        background: 'var(--surface-card)',
        paddingBottom: 'max(0.5rem, env(safe-area-inset-bottom))',
      }}
    >
      {items.map((item) => {
        const selected = item.id === value
        const Icon = ICONS[item.id]

        if (item.id === 'add') {
          return (
            <button
              key={item.id}
              type="button"
              role="tab"
              id={`tab-${item.id}`}
              aria-selected={selected}
              aria-controls={`panel-${item.id}`}
              aria-label={item.label}
              tabIndex={selected ? 0 : -1}
              className="-mt-6 flex h-12 w-12 items-center justify-center rounded-full shadow-lg"
              style={{ background: 'var(--accent)', color: 'var(--tg-button-text)' }}
              onClick={() => onChange(item.id)}
            >
              <Icon size={24} aria-hidden="true" />
            </button>
          )
        }

        return (
          <button
            key={item.id}
            type="button"
            role="tab"
            id={`tab-${item.id}`}
            aria-selected={selected}
            aria-controls={`panel-${item.id}`}
            tabIndex={selected ? 0 : -1}
            className="flex flex-1 flex-col items-center gap-1 py-2 text-xs"
            style={{ color: selected ? 'var(--accent)' : 'var(--tg-hint)', fontWeight: selected ? 600 : 400 }}
            onClick={() => onChange(item.id)}
          >
            <Icon size={20} aria-hidden="true" />
            {item.label}
          </button>
        )
      })}
    </div>
  )
}
```

- [ ] **Step 4: Run the Tabs tests to verify they pass**

Run: `cd mini-app && npx vitest run src/ui/Tabs.test.tsx`
Expected: PASS, all 4 cases (the 3 original a11y tests still pass unchanged, plus the new one).

- [ ] **Step 5: Run the full suite**

Run: `cd mini-app && npm run test`
Expected: PASS — `App.test.tsx` and `AppRoot.test.tsx` interact with tabs only through `role="tab"`/`onChange`, never through visible label text or class names.

- [ ] **Step 6: Typecheck**

Run: `cd mini-app && npm run lint`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add mini-app/src/ui/Tabs.tsx mini-app/src/ui/Tabs.test.tsx
git commit -m "feat: redesign the bottom nav as icon tabs with a raised Add button"
```

---

### Task 5: Card, chip, and button polish — Reports screen (`StaffSection`, `PeriodPicker`, `ReportsScreen` view switch)

**Files:**
- Modify: `mini-app/src/reports/StaffSection.tsx`
- Modify: `mini-app/src/reports/PeriodPicker.tsx`
- Modify: `mini-app/src/reports/ReportsScreen.tsx`

**Interfaces:** No prop or exported-type changes in any of the three files — style-only.

- [ ] **Step 1: `StaffSection` card surface**

Edit `mini-app/src/reports/StaffSection.tsx`, replace:
```tsx
      className="rounded-lg border p-3"
      style={{ borderColor: 'var(--tg-hint)' }}
```
with:
```tsx
      className="rounded-xl border p-4 shadow-sm"
      style={{ borderColor: 'var(--border)', background: 'var(--surface-card)' }}
```

- [ ] **Step 2: `PeriodPicker` selected-chip color**

Edit `mini-app/src/reports/PeriodPicker.tsx`, replace the `chipStyle` function:
```tsx
function chipStyle(selected: boolean): { background: string; color: string } {
  return {
    background: selected ? 'var(--tg-button)' : 'var(--tg-secondary-bg)',
    color: selected ? 'var(--tg-button-text)' : 'var(--tg-text)',
  }
}
```
with:
```tsx
function chipStyle(selected: boolean): { background: string; color: string } {
  return {
    background: selected ? 'var(--accent)' : 'var(--tg-secondary-bg)',
    color: selected ? 'var(--tg-button-text)' : 'var(--tg-text)',
  }
}
```

- [ ] **Step 3: `ReportsScreen` view-switch selected color**

Edit `mini-app/src/reports/ReportsScreen.tsx`, in the view-switch button's `style`, replace:
```tsx
              background: view === item.id ? 'var(--tg-button)' : 'var(--tg-secondary-bg)',
```
with:
```tsx
              background: view === item.id ? 'var(--accent)' : 'var(--tg-secondary-bg)',
```

- [ ] **Step 4: Run the affected tests**

Run: `cd mini-app && npx vitest run src/reports/StaffView.test.tsx src/reports/PeriodPicker.test.tsx src/reports/ReportsScreen.test.tsx`
Expected: PASS — these tests assert on `aria-pressed`, text content, and API calls, not on color values.

- [ ] **Step 5: Full suite + typecheck**

Run: `cd mini-app && npm run test && npm run lint`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add mini-app/src/reports/StaffSection.tsx mini-app/src/reports/PeriodPicker.tsx mini-app/src/reports/ReportsScreen.tsx
git commit -m "style: apply card and accent-color polish to the reports screen"
```

---

### Task 6: Card, chip, and button polish — Entry and History screens

**Files:**
- Modify: `mini-app/src/entry/CategoryChips.tsx`
- Modify: `mini-app/src/entry/EntryScreen.tsx`
- Modify: `mini-app/src/history/Filters.tsx`
- Modify: `mini-app/src/ui/ErrorState.tsx`
- Modify: `mini-app/src/ui/Toast.tsx`

**Interfaces:** No prop or exported-type changes in any file — style-only.

- [ ] **Step 1: `CategoryChips` selected color**

Edit `mini-app/src/entry/CategoryChips.tsx`, replace:
```tsx
              background: selected ? 'var(--tg-button)' : 'var(--tg-secondary-bg)',
              color: selected ? 'var(--tg-button-text)' : 'var(--tg-text)',
```
with:
```tsx
              background: selected ? 'var(--accent)' : 'var(--tg-secondary-bg)',
              color: selected ? 'var(--tg-button-text)' : 'var(--tg-text)',
```

- [ ] **Step 2: `EntryScreen` fallback save button**

Edit `mini-app/src/entry/EntryScreen.tsx`, replace:
```tsx
          className="mx-4 mt-2 rounded-full py-3 text-center font-semibold disabled:opacity-50"
          style={{ background: 'var(--tg-button)', color: 'var(--tg-button-text)' }}
```
with:
```tsx
          className="mx-4 mt-2 rounded-full py-3 text-center font-semibold disabled:opacity-50"
          style={{ background: 'var(--accent)', color: 'var(--tg-button-text)' }}
```

- [ ] **Step 3: `Filters` spacing (page gutter already `p-4` — normalize the select rows' visual weight to match the new card language)**

Edit `mini-app/src/history/Filters.tsx`, replace the outer wrapper:
```tsx
    <div className="flex flex-col gap-2 p-4">
```
with:
```tsx
    <div className="flex flex-col gap-3 p-4">
```

- [ ] **Step 4: `ErrorState` retry button**

Edit `mini-app/src/ui/ErrorState.tsx`, replace:
```tsx
          className="rounded-full px-4 py-2"
          style={{ background: 'var(--tg-button)', color: 'var(--tg-button-text)' }}
```
with:
```tsx
          className="rounded-full px-4 py-2"
          style={{ background: 'var(--accent)', color: 'var(--tg-button-text)' }}
```

- [ ] **Step 5: `Toast` surface**

Edit `mini-app/src/ui/Toast.tsx`, replace:
```tsx
      className="fixed inset-x-4 bottom-20 flex items-center justify-between gap-4 rounded-xl px-4 py-3 shadow-lg"
      style={{ background: 'var(--tg-button)', color: 'var(--tg-button-text)' }}
```
with:
```tsx
      className="fixed inset-x-4 bottom-20 flex items-center justify-between gap-4 rounded-xl px-4 py-3 shadow-lg"
      style={{ background: 'var(--accent)', color: 'var(--tg-button-text)' }}
```

- [ ] **Step 6: Run the affected tests**

Run: `cd mini-app && npx vitest run src/entry/EntryScreen.test.tsx src/entry/DetailsSheet.test.tsx src/history/HistoryScreen.test.tsx src/ui/ErrorState.test.tsx src/ui/Toast.test.tsx`
Expected: PASS — none of these assert on color values, only on text, roles, and form behavior. (`Filters.tsx` has no dedicated test file; it's covered indirectly through `HistoryScreen.test.tsx`.)

- [ ] **Step 7: Full suite + typecheck**

Run: `cd mini-app && npm run test && npm run lint`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add mini-app/src/entry/CategoryChips.tsx mini-app/src/entry/EntryScreen.tsx mini-app/src/history/Filters.tsx mini-app/src/ui/ErrorState.tsx mini-app/src/ui/Toast.tsx
git commit -m "style: apply accent-color polish to the entry and history screens"
```

---

### Task 7: Income/expense icons on history rows

**Files:**
- Modify: `mini-app/src/history/TransactionList.tsx`
- Create: `mini-app/src/history/TransactionList.test.tsx` (does not exist yet — `TransactionList` is currently only exercised indirectly through `HistoryScreen.test.tsx`)

**Interfaces:** No prop or exported-type changes — `TransactionListProps` unchanged, `data-testid={`transaction-${item.id}`}` unchanged.

- [ ] **Step 1: Write a test pinning the icon's presence and its type-driven choice**

Create `mini-app/src/history/TransactionList.test.tsx`. `ApiTransaction`'s full shape is in `mini-app/src/api/types.ts:33-52`; the fixture below mirrors the inline style `HistoryScreen.test.tsx` already uses (there is no shared `ApiTransaction` builder in `mini-app/src/test/fixtures.ts` to reuse):

```tsx
import { render, screen } from '@testing-library/react'
import { TransactionList } from './TransactionList'
import type { ApiTransaction } from '../api/types'

function transaction(overrides: Partial<ApiTransaction>): ApiTransaction {
  return {
    id: 1,
    type: 'expense',
    amount_minor: 120000,
    amount: '120000',
    currency: 'UZS',
    occurred_on: '2026-07-15',
    note: null,
    category: { id: 7, name: 'Taksi' },
    user: { id: 1, name: 'Malika Karimova' },
    department: null,
    dimension_values: [],
    created_at: null,
    updated_at: null,
    ...overrides,
  }
}

it('shows an income row with an up-arrow icon and an expense row with a down-arrow icon', () => {
  const items = [
    transaction({ id: 1, type: 'income' }),
    transaction({ id: 2, type: 'expense' }),
  ]

  render(
    <TransactionList items={items} exponents={{}} hasMore={false} onLoadMore={() => {}} onSelect={() => {}} />,
  )

  const incomeRow = screen.getByTestId('transaction-1')
  const expenseRow = screen.getByTestId('transaction-2')

  expect(incomeRow.querySelector('svg')).toBeInTheDocument()
  expect(expenseRow.querySelector('svg')).toBeInTheDocument()
})
```

- [ ] **Step 2: Run it to verify it fails**

Run: `cd mini-app && npx vitest run src/history/TransactionList.test.tsx`
Expected: FAIL — no `<svg>` in a row today.

- [ ] **Step 3: Add the icons**

Edit `mini-app/src/history/TransactionList.tsx`:

Add to the imports:
```tsx
import { ArrowDownLeft, ArrowUpRight } from 'lucide-react'
```

Replace the row's `<span>` block:
```tsx
            <span>
              <span className="block text-sm">{item.category.name}</span>
              <span className="block text-xs opacity-70">
                {strings.entry[item.type]} · {item.occurred_on}
              </span>
            </span>
```
with:
```tsx
            <span className="flex items-center gap-3">
              {item.type === 'income' ? (
                <ArrowUpRight size={18} style={{ color: 'var(--accent)' }} aria-hidden="true" />
              ) : (
                <ArrowDownLeft size={18} style={{ color: 'var(--accent-2)' }} aria-hidden="true" />
              )}
              <span>
                <span className="block text-sm">{item.category.name}</span>
                <span className="block text-xs opacity-70">
                  {strings.entry[item.type]} · {item.occurred_on}
                </span>
              </span>
            </span>
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd mini-app && npx vitest run src/history/TransactionList.test.tsx`
Expected: PASS.

- [ ] **Step 5: Full suite + typecheck**

Run: `cd mini-app && npm run test && npm run lint`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add mini-app/src/history/TransactionList.tsx mini-app/src/history/TransactionList.test.tsx
git commit -m "feat: show an income/expense direction icon on each history row"
```

---

### Task 8: Final verification

**Files:** None modified — verification only.

- [ ] **Step 1: Full test suite**

Run: `cd mini-app && npm run test`
Expected: PASS, every test file green.

- [ ] **Step 2: Typecheck**

Run: `cd mini-app && npm run lint`
Expected: PASS, no errors.

- [ ] **Step 3: Production build**

Run: `cd mini-app && npm run build`
Expected: succeeds, emits into `public/app` (per `vite.config.ts`'s `outDir`).

- [ ] **Step 4: Manual browser check**

Run: `cd mini-app && npm run dev`, open the printed local URL. Outside Telegram, `window.Telegram?.WebApp` is undefined, so `useTheme`'s fallback palette applies and the app renders in its non-Telegram fallback path (see `App.tsx`'s `insideTelegram` check) — session state will show the "not opened in Telegram" empty state, but the bottom nav and its colors render regardless of session state only on the `active` session branch, so this step confirms compile/paint correctness (no console errors, tokens resolve, `lucide-react` icons render) rather than the full authenticated flow. Confirm:
  - No console errors.
  - `--accent`/`--chart-*` tokens visibly resolve (not literal `var(--chart-1)` text, not transparent).
  - Toggling OS-level dark mode (or DevTools' rendering emulation for `prefers-color-scheme`) swaps the accent/chart colors to their dark values without a reload.

- [ ] **Step 5: Confirm every acceptance criterion from the design spec**

Cross-check against `docs/superpowers/specs/2026-08-01-mini-app-visual-redesign-design.md`:
  - [ ] Pie chart slices are colored per-category (Task 2).
  - [ ] Bar chart's income/expense series have distinct fixed colors (Task 3).
  - [ ] Bottom nav is icon-based with a raised center "Add" button (Task 4).
  - [ ] Cards across all three screens share the new surface/border/radius/shadow treatment (Tasks 2, 3, 5, 6).
  - [ ] No `data-testid`, `role`, or `aria-*` contract changed from what existing tests assert.

No commit for this task — it is a checklist, not a code change. If any check fails, return to the relevant task above and fix before considering the plan complete.
