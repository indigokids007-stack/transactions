import { useMemo } from 'react'
import type { ApiClient } from '../api/client'
import type { SummaryReport } from '../api/types'
import { strings } from '../strings'
import { EmptyState } from '../ui/EmptyState'
import { ErrorState } from '../ui/ErrorState'
import type { GroupRow } from './CurrencySection'
import { StaffSection } from './StaffSection'
import type { PeriodRange } from './usePeriod'
import { useReportFetch } from './useReportFetch'

export type StaffViewProps = {
  client: ApiClient
  period: PeriodRange
}

type CurrencyRanking = {
  currency: string
  rows: GroupRow[]
}

// Splits one summary report (grouped by user) into one ranking per currency, sorted
// highest spend first. A render-time calculation over data already in hand, not a
// synchronisation with anything external, so `useMemo` in the component below rather
// than a second effect — mirrors `SummaryView`'s `bucketsByCurrency` and `TrendView`'s
// `trendByCurrency`. Rows are filtered to their own currency before anything sorts or
// compares them, so one currency's ranking can never be skewed by another currency's
// figures, and nothing here could add two currencies' amounts together even by accident.
function rankByCurrency(report: SummaryReport): CurrencyRanking[] {
  const currencies = [...new Set(report.groups.map((row) => row.currency))]

  return currencies.map((currency) => ({
    currency,
    rows: report.groups
      .filter((row) => row.currency === currency)
      .slice()
      .sort((a, b) => b.amount_minor - a.amount_minor),
  }))
}

// Fetches the summary report grouped by user (`group_by=user`) for `period`, and renders
// one `StaffSection` per currency — one person's spend in one currency is never ranked
// against, let alone summed with, another currency's figures. Fetching is the one
// genuine effect; splitting by currency and ranking within it happens at render time.
// The permission check for whether this view should even be reachable lives in
// `ReportsScreen`, not here — see that file's doc comment for why.
export function StaffView({ client, period }: StaffViewProps) {
  const { data: report, failed, rateLimited, retry } = useReportFetch<SummaryReport>(
    () => client.summary({ from: period.from, to: period.to, group_by: 'user' }),
    [client, period.from, period.to],
  )

  const rankings = useMemo(() => (report ? rankByCurrency(report) : []), [report])

  if (failed) {
    return (
      <ErrorState
        message={rateLimited ? strings.reports.rateLimited : strings.reports.loadFailed}
        actionLabel={strings.common.retry}
        onAction={retry}
      />
    )
  }

  if (report && rankings.length === 0) {
    return <EmptyState message={strings.reports.empty} />
  }

  return (
    <div className="flex flex-col gap-3.5" style={{ padding: '16px 22px 130px' }}>
      {rankings.map((ranking) => (
        <StaffSection key={ranking.currency} currency={ranking.currency} rows={ranking.rows} />
      ))}
    </div>
  )
}
