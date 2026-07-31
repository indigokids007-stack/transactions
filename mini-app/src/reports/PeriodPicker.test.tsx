import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { PeriodPicker } from './PeriodPicker'
import { strings } from '../strings'
import { usePeriod } from './usePeriod'

// A thin harness around the real `usePeriod` hook, not a stub, so these tests exercise
// the picker's actual wiring to `setRange` rather than a mock that would pass no matter
// what the picker called it with. The plain text readout is the only way a test can see
// `preset`/`from`/`to` without reaching into the hook itself — a report view would read
// the same three values off this same `Period`.
function Harness() {
  const period = usePeriod(new Date('2026-07-15T00:00:00Z'))
  return (
    <div>
      <PeriodPicker period={period} />
      <p data-testid="period-state">{`${period.preset}:${period.from}:${period.to}`}</p>
    </div>
  )
}

function periodState(): string {
  return screen.getByTestId('period-state').textContent ?? ''
}

it('offers this month, last month and a custom range in one picker', () => {
  render(<Harness />)

  expect(screen.getByRole('button', { name: strings.reports.thisMonth })).toBeInTheDocument()
  expect(screen.getByRole('button', { name: strings.reports.lastMonth })).toBeInTheDocument()
  expect(screen.getByRole('button', { name: strings.reports.customRange })).toBeInTheDocument()
})

it('does not show the date inputs until custom range is chosen', () => {
  render(<Harness />)

  expect(screen.queryByLabelText(strings.reports.rangeFrom)).not.toBeInTheDocument()
  expect(screen.queryByLabelText(strings.reports.rangeTo)).not.toBeInTheDocument()
})

it('sends a chosen custom range to the period, seeded from the active preset', async () => {
  render(<Harness />)

  await userEvent.click(screen.getByRole('button', { name: strings.reports.customRange }))

  // Seeded from "this month" (the active preset when custom was chosen), not blank.
  expect(periodState()).toBe('custom:2026-07-01:2026-07-31')

  fireEvent.change(screen.getByLabelText(strings.reports.rangeFrom), { target: { value: '2026-01-05' } })
  fireEvent.change(screen.getByLabelText(strings.reports.rangeTo), { target: { value: '2026-01-20' } })

  expect(periodState()).toBe('custom:2026-01-05:2026-01-20')
})

// The review's exact requirement: an end before its start must be refused before it ever
// reaches the period (and so before any report view could fire a request for it).
it('refuses an end date before the start date', async () => {
  render(<Harness />)

  await userEvent.click(screen.getByRole('button', { name: strings.reports.customRange }))
  const before = periodState()

  fireEvent.change(screen.getByLabelText(strings.reports.rangeTo), { target: { value: '2026-01-01' } })

  expect(await screen.findByRole('alert')).toHaveTextContent(strings.reports.rangeInvalid)
  // The period never received the invalid pair — it stays exactly what it was before.
  expect(periodState()).toBe(before)
})

it('clears the invalid warning once the range is fixed', async () => {
  render(<Harness />)

  await userEvent.click(screen.getByRole('button', { name: strings.reports.customRange }))
  fireEvent.change(screen.getByLabelText(strings.reports.rangeTo), { target: { value: '2026-01-01' } })
  expect(await screen.findByRole('alert')).toBeInTheDocument()

  fireEvent.change(screen.getByLabelText(strings.reports.rangeFrom), { target: { value: '2025-12-01' } })

  expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  expect(periodState()).toBe('custom:2025-12-01:2026-01-01')
})

it('switching back to a preset hides the custom fields and reports the preset bounds', async () => {
  render(<Harness />)

  await userEvent.click(screen.getByRole('button', { name: strings.reports.customRange }))
  fireEvent.change(screen.getByLabelText(strings.reports.rangeFrom), { target: { value: '2026-01-05' } })

  await userEvent.click(screen.getByRole('button', { name: strings.reports.lastMonth }))

  expect(screen.queryByLabelText(strings.reports.rangeFrom)).not.toBeInTheDocument()
  expect(periodState()).toBe('last-month:2026-06-01:2026-06-30')
})
