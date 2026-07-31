import { act, render, screen, waitFor, within } from '@testing-library/react'
import { StaffView } from './StaffView'
import { strings } from '../strings'
import { clientReturning } from '../test/fixtures'
import type { SummaryReport } from '../api/types'

const period = { from: '2026-07-01', to: '2026-07-31' }

const report: SummaryReport = {
  totals: [],
  groups: [
    { key: '2', label: 'Alisher', currency: 'UZS', type: 'expense', amount_minor: 50000, amount: '50000', count: 1 },
    { key: '3', label: 'Bekzod', currency: 'UZS', type: 'expense', amount_minor: 90000, amount: '90000', count: 2 },
  ],
}

it('ranks people by spend within a currency', async () => {
  render(<StaffView client={clientReturning(report)} period={period} exponents={{ UZS: 0 }} />)

  const rows = await screen.findAllByTestId('staff-row')
  expect(rows[0]).toHaveTextContent('Bekzod')
  expect(rows[1]).toHaveTextContent('Alisher')
})

it('calls the api grouped by user, not by category', async () => {
  const client = clientReturning(report)
  render(<StaffView client={client} period={period} exponents={{ UZS: 0 }} />)

  await waitFor(() => expect(client.summary).toHaveBeenCalledWith(
    expect.objectContaining({ from: period.from, to: period.to, group_by: 'user' }),
  ))
})

it('says there is nothing for an empty period', async () => {
  render(<StaffView client={clientReturning({ totals: [], groups: [] })} period={period} exponents={{}} />)

  expect(await screen.findByText(strings.reports.empty)).toBeInTheDocument()
})

// The race the brief names by name, mirroring `SummaryView`/`TrendView`'s equivalent
// test: a period change fires a second request while the first is still in flight, and
// the first happens to settle *after* the second. Without the effect's `ignore`
// cleanup, the stale first response would land last and clobber the fresh one.
it('does not let a stale period response overwrite a newer one', async () => {
  const resolvers: Array<(report: SummaryReport) => void> = []
  const client = clientReturning(report)
  client.summary = vi.fn().mockImplementation(
    () => new Promise<SummaryReport>((resolve) => resolvers.push(resolve)),
  )

  const periodA = { from: '2026-07-01', to: '2026-07-31' }
  const periodB = { from: '2026-08-01', to: '2026-08-31' }

  const { rerender } = render(<StaffView client={client} period={periodA} exponents={{ UZS: 0 }} />)
  rerender(<StaffView client={client} period={periodB} exponents={{ UZS: 0 }} />)

  await waitFor(() => expect(resolvers).toHaveLength(2))

  const staleReport: SummaryReport = {
    totals: [],
    groups: [{ key: '9', label: 'Stale', currency: 'UZS', type: 'expense', amount_minor: 111, amount: '111', count: 1 }],
  }
  const freshReport: SummaryReport = {
    totals: [],
    groups: [{ key: '9', label: 'Fresh', currency: 'UZS', type: 'expense', amount_minor: 222, amount: '222', count: 1 }],
  }

  // The second (fresher) request settles first ...
  resolvers[1](freshReport)
  await screen.findByText('Fresh')

  // ... and only afterwards does the first (now-stale) request settle.
  await act(async () => {
    resolvers[0](staleReport)
    await Promise.resolve()
    await Promise.resolve()
  })

  expect(screen.getByText('Fresh')).toBeInTheDocument()
  expect(screen.queryByText('Stale')).not.toBeInTheDocument()
})

// The rule that outranks everything: money of different currencies is never summed, and
// one currency's ranking never absorbs another currency's rows. A bucket that forgot to
// filter by currency before ranking would pull Davron (USD) straight into UZS's list —
// mixed in among Bekzod and Alisher, or vice versa — rather than merely reordering rows
// that already belonged to the same currency. Names are the discriminator here (not
// formatted amounts, as in Task 6/7's charts), since ranking rows carry no chart to leak
// through — a name appearing in the wrong section is the same kind of unmistakable,
// currency-specific tell.
it("never lets one currency's ranking absorb another currency's rows", async () => {
  const mixedReport: SummaryReport = {
    totals: [],
    groups: [
      { key: '2', label: 'Alisher', currency: 'UZS', type: 'expense', amount_minor: 50000, amount: '50000', count: 1 },
      { key: '3', label: 'Bekzod', currency: 'UZS', type: 'expense', amount_minor: 90000, amount: '90000', count: 2 },
      { key: '4', label: 'Davron', currency: 'USD', type: 'expense', amount_minor: 4200, amount: '42.00', count: 1 },
    ],
  }

  render(<StaffView client={clientReturning(mixedReport)} period={period} exponents={{ UZS: 0, USD: 2 }} />)

  const uzs = await screen.findByTestId('staff-UZS')
  const usd = await screen.findByTestId('staff-USD')

  expect(await within(uzs).findByText('Bekzod')).toBeInTheDocument()
  expect(await within(uzs).findByText('Alisher')).toBeInTheDocument()
  expect(await within(usd).findByText('Davron')).toBeInTheDocument()

  // Neither section may contain a person who only belongs in the other currency's
  // ranking.
  expect(within(uzs).queryByText('Davron')).not.toBeInTheDocument()
  expect(within(usd).queryByText('Bekzod')).not.toBeInTheDocument()
  expect(within(usd).queryByText('Alisher')).not.toBeInTheDocument()
})
