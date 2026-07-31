import { Bar, BarChart, CartesianGrid, Legend, LabelList, ResponsiveContainer, XAxis, YAxis } from 'recharts'
import type { RenderableText } from 'recharts/types/component/Text'
import { strings } from '../strings'
import { formatMoney } from '../ui/Money'

export type TrendPoint = {
  period: string
  income?: number
  expense?: number
}

export type TrendSectionProps = {
  currency: string
  series: TrendPoint[]
  exponents: Record<string, number>
  /** See `CurrencySection`'s doc comment: fixed dimensions short-circuit jsdom's lack of `ResizeObserver`. */
  chartWidth?: number
  chartHeight?: number
}

function formatLabel(value: RenderableText, currency: string, exponents: Record<string, number>): RenderableText {
  return typeof value === 'number' ? formatMoney(value, currency, exponents) : value
}

// One currency's slice of a trend report: its own bar chart, never another currency's
// series. The caller (`TrendView`) is the only place currencies are split apart, and this
// component has no way to add two of them back together — it only ever sees the one
// `series` array it was handed.
export function TrendSection({ currency, series, exponents, chartWidth, chartHeight }: TrendSectionProps) {
  return (
    <section
      data-testid={`trend-${currency}`}
      className="rounded-lg border p-3"
      style={{ borderColor: 'var(--tg-hint)' }}
    >
      <h3 className="text-sm font-semibold">{currency}</h3>

      <div className="mt-2">
        <ResponsiveContainer width={chartWidth ?? '100%'} height={chartHeight ?? 220}>
          <BarChart data={series}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="period" />
            <YAxis />
            <Legend />
            <Bar dataKey="income" name={strings.entry.income} fill="var(--tg-button)" isAnimationActive={false}>
              <LabelList dataKey="income" position="top" formatter={(value) => formatLabel(value, currency, exponents)} />
            </Bar>
            <Bar dataKey="expense" name={strings.entry.expense} fill="var(--tg-hint)" isAnimationActive={false}>
              <LabelList dataKey="expense" position="top" formatter={(value) => formatLabel(value, currency, exponents)} />
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
    </section>
  )
}
