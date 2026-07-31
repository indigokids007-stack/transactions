import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ReportsScreen } from './ReportsScreen'
import { strings } from '../strings'
import { clientStub, managerUser, staffUser } from '../test/fixtures'
import type { ApiClient } from '../api/client'
import type { ApiUser, SummaryReport, TrendReport } from '../api/types'
import { usePeriod, type Period } from './usePeriod'

const period: Period = {
  from: '2026-07-01',
  to: '2026-07-31',
  preset: 'this-month',
  setPreset: vi.fn(),
  setRange: vi.fn(),
}

const summary: SummaryReport = {
  totals: [{ currency: 'UZS', type: 'expense', amount_minor: 100, amount: '100', count: 1 }],
  groups: [{ key: '7', label: 'Taksi', currency: 'UZS', type: 'expense', amount_minor: 100, amount: '100', count: 1 }],
}

const trend: TrendReport = {
  points: [{ period: '2026-07-01', currency: 'UZS', type: 'expense', amount_minor: 100, amount: '100', count: 1 }],
}

const rest = {
  client: clientStub({ summary: vi.fn().mockResolvedValue(summary), trend: vi.fn().mockResolvedValue(trend) }),
  period,
  dimensions: [],
}

it('offers the staff comparison to a manager', async () => {
  render(<ReportsScreen user={managerUser} {...rest} />)

  expect(await screen.findByRole('button', { name: strings.reports.byStaff })).toBeInTheDocument()
})

it('hides the staff comparison from a staff member', async () => {
  render(<ReportsScreen user={staffUser} {...rest} />)

  // `findByTestId` (rather than the synchronous `getByTestId`) lets the summary view's
  // own fetch effect settle inside `act` before the test ends, the same way the other
  // scenarios below do — otherwise React logs an act() warning for a state update that
  // arrives after this synchronous assertion already returned.
  await screen.findByTestId('currency-UZS')
  expect(screen.queryByRole('button', { name: strings.reports.byStaff })).not.toBeInTheDocument()
})

it('shows the summary view by default', async () => {
  const client = clientStub({
    summary: vi.fn().mockResolvedValue(summary),
    trend: vi.fn().mockResolvedValue(trend),
  })
  render(<ReportsScreen user={managerUser} client={client} period={period} />)

  expect(await screen.findByTestId('currency-UZS')).toBeInTheDocument()
  expect(screen.queryByTestId('trend-UZS')).not.toBeInTheDocument()
})

it('switches to the trend view on click and back', async () => {
  const client = clientStub({
    summary: vi.fn().mockResolvedValue(summary),
    trend: vi.fn().mockResolvedValue(trend),
  })
  render(<ReportsScreen user={managerUser} client={client} period={period} />)

  await screen.findByTestId('currency-UZS')
  await userEvent.click(screen.getByRole('button', { name: strings.reports.byTrend }))

  expect(await screen.findByTestId('trend-UZS')).toBeInTheDocument()
  expect(screen.queryByTestId('currency-UZS')).not.toBeInTheDocument()

  await userEvent.click(screen.getByRole('button', { name: strings.reports.bySummary }))

  expect(await screen.findByTestId('currency-UZS')).toBeInTheDocument()
  expect(screen.queryByTestId('trend-UZS')).not.toBeInTheDocument()
})

it('switches to the staff comparison for a manager', async () => {
  const client = clientStub({
    summary: vi.fn().mockResolvedValue(summary),
    trend: vi.fn().mockResolvedValue(trend),
  })
  render(<ReportsScreen user={managerUser} client={client} period={period} />)

  await screen.findByTestId('currency-UZS')
  await userEvent.click(screen.getByRole('button', { name: strings.reports.byStaff }))

  expect(await screen.findAllByTestId('staff-row')).toHaveLength(1)
})

// A staff member has no way to reach the comparison at all — not just a hidden button,
// but no route to the underlying fetch either, since `StaffView` is never mounted for
// them (the `hides the staff comparison` test above only proves the button is absent;
// this proves the client is never even asked).
it('never fetches the staff comparison for a user who cannot see others', async () => {
  const client = clientStub({
    summary: vi.fn().mockResolvedValue(summary),
    trend: vi.fn().mockResolvedValue(trend),
  })
  render(<ReportsScreen user={staffUser} client={client} period={period} />)

  await screen.findByTestId('currency-UZS')

  expect(client.summary).not.toHaveBeenCalledWith(expect.objectContaining({ group_by: 'user' }))
})

// A real `usePeriod`, not the fixed stub the tests above use, so the picker's own
// `setRange` wiring is exercised end to end: the review's finding was that nothing
// reached `setRange` from Reports at all.
function ScreenWithRealPeriod({ user, client }: { user: ApiUser; client: ApiClient }) {
  const period = usePeriod(new Date('2026-07-15T00:00:00Z'))
  return <ReportsScreen user={user} client={client} period={period} dimensions={[]} />
}

it('sends a chosen custom range to the summary report', async () => {
  const client = clientStub({ summary: vi.fn().mockResolvedValue(summary), trend: vi.fn().mockResolvedValue(trend) })
  render(<ScreenWithRealPeriod user={managerUser} client={client} />)

  await screen.findByTestId('currency-UZS')

  await userEvent.click(screen.getByRole('button', { name: strings.reports.customRange }))
  fireEvent.change(screen.getByLabelText(strings.reports.rangeFrom), { target: { value: '2026-01-05' } })
  fireEvent.change(screen.getByLabelText(strings.reports.rangeTo), { target: { value: '2026-01-20' } })

  await waitFor(() =>
    expect(client.summary).toHaveBeenLastCalledWith(
      expect.objectContaining({ from: '2026-01-05', to: '2026-01-20' }),
    ),
  )
})

// The review's other half of the same finding: an invalid range must never reach the
// api, not merely be corrected somewhere downstream.
it('refuses an invalid custom range before making any request', async () => {
  const client = clientStub({ summary: vi.fn().mockResolvedValue(summary), trend: vi.fn().mockResolvedValue(trend) })
  render(<ScreenWithRealPeriod user={managerUser} client={client} />)

  await screen.findByTestId('currency-UZS')
  expect(client.summary).toHaveBeenCalledTimes(1)

  await userEvent.click(screen.getByRole('button', { name: strings.reports.customRange }))
  fireEvent.change(screen.getByLabelText(strings.reports.rangeTo), { target: { value: '2026-01-01' } })

  expect(await screen.findByRole('alert')).toHaveTextContent(strings.reports.rangeInvalid)
  expect(client.summary).toHaveBeenCalledTimes(1)
})
