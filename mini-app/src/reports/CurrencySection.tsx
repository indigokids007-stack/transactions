import { Legend, Pie, PieChart, ResponsiveContainer } from 'recharts'
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

// One currency's slice of a summary report: its own totals, its own pie chart. Never
// receives another currency's rows, and never combines with one — the caller
// (`SummaryView`) is the only place currencies are split apart, and this component has
// no way to add two of them back together.
export function CurrencySection({ currency, totals, groups, chartWidth, chartHeight }: CurrencySectionProps) {
  return (
    <section data-testid={`currency-${currency}`} className="rounded-lg border p-3" style={{ borderColor: 'var(--tg-hint)' }}>
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
            <Pie data={groups} dataKey="amount_minor" nameKey="label" isAnimationActive={false} />
            <Legend />
          </PieChart>
        </ResponsiveContainer>
      </div>
    </section>
  )
}
