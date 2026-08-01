import { useMemo, useState } from 'react'
import type { ApiClient } from '../api/client'
import type { ApiDimension, SummaryReport } from '../api/types'
import { strings } from '../strings'
import { EmptyState } from '../ui/EmptyState'
import { ErrorState } from '../ui/ErrorState'
import { CurrencySection, type GroupRow } from './CurrencySection'
import type { PeriodRange } from './usePeriod'
import { useReportFetch } from './useReportFetch'

export type SummaryViewProps = {
  client: ApiClient
  period: PeriodRange
  /** Every active dimension the group-by switch should offer, alongside category. */
  dimensions?: ApiDimension[]
}

type CurrencyBucket = {
  currency: string
  totals: SummaryReport['totals']
  groups: GroupRow[]
}

// Splits one report into one bucket per currency. A render-time calculation over data
// already in hand, not a synchronisation with anything external, so it is `useMemo` in
// the component below rather than a second effect.
function bucketsByCurrency(report: SummaryReport): CurrencyBucket[] {
  const currencies = [...new Set(report.totals.map((row) => row.currency))]

  return currencies.map((currency) => ({
    currency,
    totals: report.totals.filter((row) => row.currency === currency),
    groups: report.groups.filter((row) => row.currency === currency),
  }))
}

function groupByValue(dimensionKey: string): string {
  return `dimension:${dimensionKey}`
}

// Fetches the summary report for `period` and `groupBy`, and renders one `CurrencySection`
// per currency in the response — money of different currencies is never added together,
// so there is nothing here that could sum across them even by accident. Fetching is the
// one genuine effect: everything else (splitting the response by currency) is derived at
// render time.
export function SummaryView({ client, period, dimensions = [] }: SummaryViewProps) {
  const [groupBy, setGroupBy] = useState('category')

  const { data: report, failed, rateLimited, retry } = useReportFetch<SummaryReport>(
    () => client.summary({ from: period.from, to: period.to, group_by: groupBy }),
    [client, period.from, period.to, groupBy],
  )

  const buckets = useMemo(() => (report ? bucketsByCurrency(report) : []), [report])

  const groupBySelect = (
    <label
      className="flex items-center justify-between"
      style={{
        background: 'var(--surface)',
        borderRadius: 18,
        padding: '13px 16px',
        font: '600 12px/1 "Plus Jakarta Sans"',
        color: 'var(--muted)',
        boxShadow: 'var(--shadow-card-sm)',
      }}
    >
      {strings.reports.groupBy}
      <select
        value={groupBy}
        onChange={(event) => setGroupBy(event.target.value)}
        style={{ border: 0, background: 'transparent', font: '700 13px/1 "Plus Jakarta Sans"', color: 'var(--teal-900)', outline: 'none', textAlign: 'right' }}
      >
        <option value="category">{strings.reports.category}</option>
        {dimensions.map((dimension) => (
          <option key={dimension.id} value={groupByValue(dimension.key)}>
            {dimension.name}
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
    <div className="flex flex-col gap-3.5" style={{ padding: '16px 22px 130px' }}>
      {groupBySelect}

      {buckets.map((bucket) => (
        <CurrencySection key={bucket.currency} currency={bucket.currency} totals={bucket.totals} groups={bucket.groups} />
      ))}
    </div>
  )
}
