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
    <div className="flex flex-col">
      <div
        style={{
          background: 'var(--surface)',
          padding: 'calc(54px + max(env(safe-area-inset-top), var(--tg-content-safe-top))) 22px 18px',
          borderRadius: '0 0 28px 28px',
        }}
      >
        <h2
          className="m-0"
          style={{ font: '800 26px/1.1 "Plus Jakarta Sans"', color: 'var(--ink)', letterSpacing: '-.02em', marginBottom: 14 }}
        >
          {strings.tabs.reports}
        </h2>

        <PeriodPicker period={period} />

        <div
          role="group"
          aria-label={strings.reports.view}
          className="flex"
          style={{ background: 'var(--pill-bg)', borderRadius: 999, padding: 4, marginTop: 16 }}
        >
          {views.map((item) => (
            <button
              key={item.id}
              type="button"
              aria-pressed={view === item.id}
              onClick={() => setView(item.id)}
              className="flex-1 rounded-full"
              style={{
                border: 0,
                padding: '11px 8px',
                font: '700 12px/1 "Plus Jakarta Sans"',
                background: view === item.id ? 'var(--surface)' : 'transparent',
                color: view === item.id ? 'var(--teal-900)' : '#8aa79f',
                boxShadow: view === item.id ? '0 3px 10px rgba(16,72,63,.1)' : undefined,
              }}
            >
              {item.label}
            </button>
          ))}
        </div>
      </div>

      {view === 'summary' && <SummaryView client={client} period={period} dimensions={dimensions} />}
      {view === 'trend' && <TrendView client={client} period={period} />}
      {view === 'staff' && canSeeStaff && <StaffView client={client} period={period} />}
    </div>
  )
}
