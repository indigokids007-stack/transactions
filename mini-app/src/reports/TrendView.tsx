import { useMemo, useState } from 'react'
import type { ApiClient } from '../api/client'
import type { TrendReport } from '../api/types'
import { strings } from '../strings'
import { EmptyState } from '../ui/EmptyState'
import { ErrorState } from '../ui/ErrorState'
import { TrendSection, type TrendPoint } from './TrendSection'
import type { PeriodRange } from './usePeriod'
import { useReportFetch } from './useReportFetch'

export type TrendInterval = 'day' | 'week' | 'month'

export type TrendViewProps = {
  client: ApiClient
  period: PeriodRange
  /** Forwarded to `TrendSection` — see its own doc comment. Only ever set in tests. */
  chartWidth?: number
  chartHeight?: number
}

type CurrencyTrend = {
  currency: string
  series: TrendPoint[]
}

const INTERVALS: TrendInterval[] = ['day', 'week', 'month']

// Splits one trend report into one series per currency, one row per period, income and
// expense living in separate object keys within that row. A render-time reshaping of
// data already in hand, not a synchronisation with anything external, so `useMemo` in the
// component below rather than a second effect. Filters by `currency` before anything else
// touches the rows, so a bucket for one currency can never see another's — the two type
// keys never add to each other either, since each is a plain assignment into its own
// property, not a running total.
function trendByCurrency(report: TrendReport): CurrencyTrend[] {
  const currencies = [...new Set(report.points.map((point) => point.currency))]

  return currencies.map((currency) => {
    const rows = report.points.filter((point) => point.currency === currency)
    const periods = [...new Set(rows.map((row) => row.period))]

    const series = periods.map((period) => {
      const point: TrendPoint = { period }
      rows
        .filter((row) => row.period === period)
        .forEach((row) => {
          // Branched rather than a computed `point[row.type] = ...` so both the bar's
          // number and its label's exact string land on the matching pair of keys —
          // `income`/`incomeAmount` or `expense`/`expenseAmount` — without an `any`-typed
          // dynamic key.
          if (row.type === 'income') {
            point.income = row.amount_minor
            point.incomeAmount = row.amount
          } else {
            point.expense = row.amount_minor
            point.expenseAmount = row.amount
          }
        })
      return point
    })

    return { currency, series }
  })
}

// Fetches the trend report for `period` and `interval`, and renders one `TrendSection`
// per currency in the response — money of different currencies never shares a chart, let
// alone an accumulator, so nothing here could sum across them even by accident. Fetching
// is the one genuine effect: splitting the response by currency and reshaping it into a
// per-period series happens at render time.
export function TrendView({ client, period, chartWidth, chartHeight }: TrendViewProps) {
  const [interval, setIntervalValue] = useState<TrendInterval>('day')

  const { data: report, failed, rateLimited, retry } = useReportFetch<TrendReport>(
    () => client.trend({ from: period.from, to: period.to, interval }),
    [client, period.from, period.to, interval],
  )

  const buckets = useMemo(() => (report ? trendByCurrency(report) : []), [report])

  const intervalSelect = (
    <label className="block text-sm">
      {strings.reports.interval}
      <select
        value={interval}
        onChange={(event) => setIntervalValue(event.target.value as TrendInterval)}
        className="mt-1 block w-full rounded border px-2 py-1"
      >
        {INTERVALS.map((value) => (
          <option key={value} value={value}>
            {strings.reports[value]}
          </option>
        ))}
      </select>
    </label>
  )

  if (failed) {
    return (
      <ErrorState
        message={rateLimited ? strings.reports.rateLimited : strings.reports.loadFailed}
        actionLabel={strings.common.retry}
        onAction={retry}
      />
    )
  }

  if (report && buckets.length === 0) {
    return <EmptyState message={strings.reports.empty} />
  }

  return (
    <div className="flex flex-col gap-4 p-4">
      {intervalSelect}

      {buckets.map((bucket) => (
        <TrendSection
          key={bucket.currency}
          currency={bucket.currency}
          series={bucket.series}
          chartWidth={chartWidth}
          chartHeight={chartHeight}
        />
      ))}
    </div>
  )
}
