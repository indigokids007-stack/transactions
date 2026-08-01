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
