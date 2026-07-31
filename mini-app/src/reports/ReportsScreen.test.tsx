import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ReportsScreen } from './ReportsScreen'
import { strings } from '../strings'
import { clientStub, managerUser, staffUser } from '../test/fixtures'
import type { SummaryReport, TrendReport } from '../api/types'
import type { Period } from './usePeriod'

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
  exponents: { UZS: 0 },
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
  render(<ReportsScreen user={managerUser} client={client} period={period} exponents={{ UZS: 0 }} />)

  expect(await screen.findByTestId('currency-UZS')).toBeInTheDocument()
  expect(screen.queryByTestId('trend-UZS')).not.toBeInTheDocument()
})

it('switches to the trend view on click and back', async () => {
  const client = clientStub({
    summary: vi.fn().mockResolvedValue(summary),
    trend: vi.fn().mockResolvedValue(trend),
  })
  render(<ReportsScreen user={managerUser} client={client} period={period} exponents={{ UZS: 0 }} />)

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
  render(<ReportsScreen user={managerUser} client={client} period={period} exponents={{ UZS: 0 }} />)

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
  render(<ReportsScreen user={staffUser} client={client} period={period} exponents={{ UZS: 0 }} />)

  await screen.findByTestId('currency-UZS')

  expect(client.summary).not.toHaveBeenCalledWith(expect.objectContaining({ group_by: 'user' }))
})
