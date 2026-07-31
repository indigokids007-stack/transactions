import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { App } from './App'
import { strings } from './strings'
// Reused rather than redefined: `activeSession`, `managerSession` and `pendingUser`
// already carry the exact shapes this suite needs (staff vs. manager permissions, a
// pending status), per Task 2's/Task 8's fixture kit.
import { activeSession, clientStub, managerSession, pendingUser } from './test/fixtures'
import type { SummaryReport, TrendReport } from './api/types'

const summaryReport: SummaryReport = {
  totals: [{ currency: 'UZS', type: 'expense', amount_minor: 100, amount: '100', count: 1 }],
  groups: [{ key: '7', label: 'Taksi', currency: 'UZS', type: 'expense', amount_minor: 100, amount: '100', count: 1 }],
}

const trendReport: TrendReport = {
  points: [{ period: '2026-07-01', currency: 'UZS', type: 'expense', amount_minor: 100, amount: '100', count: 1 }],
}

it('shows the loading screen and no tabs', () => {
  render(<App session={{ kind: 'loading' }} />)

  expect(screen.getByText(strings.session.loading)).toBeInTheDocument()
  expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
})

it('shows the waiting screen for a pending user and no tabs', () => {
  render(<App session={{ kind: 'pending', user: pendingUser }} />)

  expect(screen.getByText(strings.session.pending)).toBeInTheDocument()
  expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
})

it('shows the refusal message and no tabs', () => {
  render(<App session={{ kind: 'refused', message: 'Registration is closed.' }} />)

  expect(screen.getByText('Registration is closed.')).toBeInTheDocument()
  expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
})

it('shows the error message with a retry action and no tabs', async () => {
  const onRetry = vi.fn()
  const user = userEvent.setup()
  render(<App session={{ kind: 'error', message: 'Server exploded.' }} onRetry={onRetry} />)

  expect(screen.getByText('Server exploded.')).toBeInTheDocument()
  expect(screen.queryByRole('tablist')).not.toBeInTheDocument()

  await user.click(screen.getByRole('button', { name: strings.common.retry }))
  expect(onRetry).toHaveBeenCalledOnce()
})

it('renders the three tabs for an active user', () => {
  render(<App session={activeSession} />)

  expect(screen.getByRole('tab', { name: strings.tabs.add })).toBeInTheDocument()
  expect(screen.getByRole('tab', { name: strings.tabs.reports })).toBeInTheDocument()
  expect(screen.getByRole('tab', { name: strings.tabs.history })).toBeInTheDocument()
})

// The Reports tab renders `ReportsScreen` (Task 8), which owns its own view switch —
// there is no app-level "staff" tab to query for; the switch lives inside the Reports
// panel and is gated by `permissions.can_see_all`/`can_manage`. This is the end-to-end
// proof that a manager can actually open the trend and staff views a person can reach,
// not just that the underlying components exist in isolation.
it('lets a manager reach the trend and staff comparison through the Reports tab', async () => {
  const user = userEvent.setup()
  const client = clientStub({
    summary: vi.fn().mockResolvedValue(summaryReport),
    trend: vi.fn().mockResolvedValue(trendReport),
  })
  render(<App session={managerSession} client={client} />)

  await user.click(screen.getByRole('tab', { name: strings.tabs.reports }))
  await screen.findByTestId('currency-UZS')

  await user.click(screen.getByRole('button', { name: strings.reports.byTrend }))
  expect(await screen.findByTestId('trend-UZS')).toBeInTheDocument()

  await user.click(screen.getByRole('button', { name: strings.reports.byStaff }))
  expect(await screen.findAllByTestId('staff-row')).not.toHaveLength(0)
})

// The other half of the same proof: a plain staff member reaches the trend view (no
// permission needed) through the very same tab, but the staff comparison is not just
// hidden — the switch button for it never renders at all.
it('lets a staff member reach the trend view but never the staff comparison', async () => {
  const user = userEvent.setup()
  const client = clientStub({
    summary: vi.fn().mockResolvedValue(summaryReport),
    trend: vi.fn().mockResolvedValue(trendReport),
  })
  render(<App session={activeSession} client={client} />)

  await user.click(screen.getByRole('tab', { name: strings.tabs.reports }))
  await screen.findByTestId('currency-UZS')

  expect(screen.queryByRole('button', { name: strings.reports.byStaff })).not.toBeInTheDocument()

  await user.click(screen.getByRole('button', { name: strings.reports.byTrend }))
  expect(await screen.findByTestId('trend-UZS')).toBeInTheDocument()
})

// Task 8's own retrospective: Tasks 7 and 8 built views no user could actually open,
// because nothing wired them into a tab. This is the same proof for Task 9 — the History
// tab renders `HistoryScreen` and its data reaches the screen, not a placeholder.
it('lets a user reach the history list through the History tab', async () => {
  const user = userEvent.setup()
  const client = clientStub({
    listTransactions: vi.fn().mockResolvedValue({
      data: [
        {
          id: 1,
          type: 'expense',
          amount_minor: 50000,
          amount: '50000',
          currency: 'UZS',
          occurred_on: '2026-07-20',
          note: null,
          category: { id: 7, name: 'Taksi' },
          user: { id: 1, name: 'Malika Karimova' },
          department: null,
          dimension_values: [],
          created_at: null,
          updated_at: null,
        },
      ],
      meta: { next_cursor: null },
    }),
  })
  render(<App session={activeSession} client={client} />)

  await user.click(screen.getByRole('tab', { name: strings.tabs.history }))

  expect(await screen.findByTestId('transaction-1')).toBeInTheDocument()
})

it('has an aria-controls target that resolves to an element in the document, for every tab', () => {
  render(<App session={activeSession} />)

  const tabs = screen.getAllByRole('tab')
  expect(tabs).toHaveLength(3)

  for (const tab of tabs) {
    const controlsId = tab.getAttribute('aria-controls')
    expect(controlsId).toBeTruthy()
    expect(document.getElementById(controlsId as string)).not.toBeNull()
  }
})

it('keeps a tab mounted (not torn down) so its state survives switching away and back', async () => {
  const user = userEvent.setup()
  render(<App session={activeSession} />)

  const addInput = screen.getByRole('textbox', { name: strings.tabs.add })
  await user.type(addInput, 'tuzatildi')
  expect(addInput).toHaveValue('tuzatildi')

  await user.click(screen.getByRole('tab', { name: strings.tabs.history }))
  await user.click(screen.getByRole('tab', { name: strings.tabs.add }))

  expect(screen.getByRole('textbox', { name: strings.tabs.add })).toHaveValue('tuzatildi')
})
