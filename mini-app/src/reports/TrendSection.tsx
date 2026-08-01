import { Bar, BarChart, CartesianGrid, Legend, LabelList, ResponsiveContainer, XAxis, YAxis } from 'recharts'
import type { CartesianLabelListEntry, PolarLabelListEntry } from 'recharts/types/component/LabelList'
import { strings } from '../strings'
import { CHART_SCALED_CURRENCY, formatMoneyString, toChartThousands } from '../ui/Money'

export type TrendPoint = {
  period: string
  // `income`/`expense` only drive each bar's pixel height — recharts needs a number for
  // that, and a height a few units off at these magnitudes is invisible. The text a
  // person actually reads comes from `incomeAmount`/`expenseAmount` below instead.
  income?: number
  expense?: number
  // The backend's precision-safe decimal string for the same value (`AggregateRow.amount`
  // in `api/types.ts`). A trend point can sum many transactions into one bar, so, like
  // `CurrencySection`'s totals, its label must not be read off the JSON number.
  incomeAmount?: string
  expenseAmount?: string
}

export type TrendSectionProps = {
  currency: string
  series: TrendPoint[]
  /** See `CurrencySection`'s doc comment: fixed dimensions short-circuit jsdom's lack of `ResizeObserver`. */
  chartWidth?: number
  chartHeight?: number
}

function isTrendPoint(value: unknown): value is TrendPoint {
  return typeof value === 'object' && value !== null && 'period' in value
}

// A `LabelList` `valueAccessor` (rather than `formatter`, which only ever sees the raw
// `income`/`expense` number) reaches the whole data point through `entry.payload`, so it
// can read the exact string field instead. Recharts types `payload` as the same union for
// every chart kind, hence the runtime guard rather than a cast. `currency` decides which
// formatter reads that string — `toChartThousands` still reads the exact `amount`, never
// the lossy `amount_minor` number, so the precision guarantee holds at any scale.
function labelValue(key: 'incomeAmount' | 'expenseAmount', currency: string) {
  return (entry: CartesianLabelListEntry | PolarLabelListEntry): string => {
    const payload: unknown = entry.payload
    if (!isTrendPoint(payload)) return ''
    const amount = payload[key]
    if (amount === undefined) return ''
    return currency === CHART_SCALED_CURRENCY ? toChartThousands(amount) : formatMoneyString(amount)
  }
}

// Recharts calls this with the axis's own generated tick values — already the "nice"
// round numbers it chose for the gridlines, not a real transaction's amount — so plain
// number arithmetic is fine here; there is no precision guarantee to protect at a gridline.
function scaledAxisTick(value: number): string {
  return String(Math.round(value / 1000))
}

// One currency's slice of a trend report: its own bar chart, never another currency's
// series. The caller (`TrendView`) is the only place currencies are split apart, and this
// component has no way to add two of them back together — it only ever sees the one
// `series` array it was handed.
export function TrendSection({ currency, series, chartWidth, chartHeight }: TrendSectionProps) {
  return (
    <section
      data-testid={`trend-${currency}`}
      className="rounded-xl border p-4 shadow-sm"
      style={{ borderColor: 'var(--border)', background: 'var(--surface-card)' }}
    >
      <h3 className="text-sm font-semibold">{currency}</h3>

      <div className="mt-2">
        <ResponsiveContainer width={chartWidth ?? '100%'} height={chartHeight ?? 220}>
          <BarChart data={series}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="period" />
            <YAxis tickFormatter={currency === CHART_SCALED_CURRENCY ? scaledAxisTick : undefined} />
            <Legend />
            <Bar dataKey="income" name={strings.entry.income} fill="var(--accent)" isAnimationActive={false}>
              <LabelList position="top" valueAccessor={labelValue('incomeAmount', currency)} />
            </Bar>
            <Bar dataKey="expense" name={strings.entry.expense} fill="var(--accent-2)" isAnimationActive={false}>
              <LabelList position="top" valueAccessor={labelValue('expenseAmount', currency)} />
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
    </section>
  )
}
