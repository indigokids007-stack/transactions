import { useState } from 'react'
import type { ApiClient } from '../api/client'
import type { ApiDimension, ApiUser } from '../api/types'
import { strings } from '../strings'
import { PeriodPicker } from './PeriodPicker'
import { StaffView } from './StaffView'
import { SummaryView } from './SummaryView'
import { TrendView } from './TrendView'
import type { Period } from './usePeriod'

export type ReportsScreenProps = {
  user: ApiUser
  client: ApiClient
  period: Period
  dimensions?: ApiDimension[]
}

type ReportView = 'summary' | 'trend' | 'staff'

type ViewOption = { id: ReportView; label: string }

const BASE_VIEWS: ViewOption[] = [
  { id: 'summary', label: strings.reports.bySummary },
  { id: 'trend', label: strings.reports.byTrend },
]

// The single gate for the staff comparison: reachable only for someone who may see
// other people's spend at all. Checked here, in the one place a caller can audit, rather
// than inside `StaffView` — the API enforces its own scope regardless of what the client
// asks for, so a mistake here could only ever show a confusing view (a switch that
// returns just one's own row), never leak another user's data.
function canSeeStaffComparison(user: ApiUser): boolean {
  return user.permissions.can_see_all || user.permissions.can_manage
}

// Switches between the three report views (Task 8): the currency-by-currency summary,
// the per-currency trend, and — only for a user who may see other people's spend — the
// staff comparison. Holds the active view as its own state, entirely separate from
// `strings.tabs`: the switch below is a set of buttons inside the Reports panel, not a
// second row of app-level tabs.
export function ReportsScreen({ user, client, period, dimensions = [] }: ReportsScreenProps) {
  const canSeeStaff = canSeeStaffComparison(user)
  const views = canSeeStaff ? [...BASE_VIEWS, { id: 'staff' as const, label: strings.reports.byStaff }] : BASE_VIEWS

  const [view, setView] = useState<ReportView>('summary')

  return (
    <div className="flex flex-col gap-3">
      <div
        className="mx-4 rounded-xl border p-4 shadow-sm"
        style={{ background: 'var(--surface-card)', borderColor: 'var(--border)' }}
      >
        <PeriodPicker period={period} />
      </div>

      <div role="group" aria-label={strings.reports.view} className="flex gap-2 px-4">
        {views.map((item) => (
          <button
            key={item.id}
            type="button"
            aria-pressed={view === item.id}
            onClick={() => setView(item.id)}
            className="rounded-full px-3 py-1 text-sm"
            style={{
              background: view === item.id ? 'var(--accent)' : 'var(--tg-secondary-bg)',
              color: view === item.id ? 'var(--accent-text)' : 'var(--tg-text)',
            }}
          >
            {item.label}
          </button>
        ))}
      </div>

      {view === 'summary' && <SummaryView client={client} period={period} dimensions={dimensions} />}
      {view === 'trend' && <TrendView client={client} period={period} />}
      {view === 'staff' && canSeeStaff && <StaffView client={client} period={period} />}
    </div>
  )
}
