import { act, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { TrendView } from './TrendView'
import { strings } from '../strings'
import { clientReturning } from '../test/fixtures'
import type { TrendReport } from '../api/types'

const period = { from: '2026-07-01', to: '2026-07-31' }

const trend: TrendReport = {
  points: [
    { period: '2026-07-01', currency: 'UZS', type: 'expense', amount_minor: 100, amount: '100', count: 1 },
    { period: '2026-07-02', currency: 'UZS', type: 'expense', amount_minor: 200, amount: '200', count: 1 },
    { period: '2026-07-02', currency: 'USD', type: 'expense', amount_minor: 500, amount: '5.00', count: 1 },
  ],
}

it('draws one chart per currency', async () => {
  render(<TrendView client={clientReturning(trend)} period={period} exponents={{ UZS: 0, USD: 2 }} />)

  expect(await screen.findByTestId('trend-UZS')).toBeInTheDocument()
  expect(await screen.findByTestId('trend-USD')).toBeInTheDocument()
})

it('asks the api for the chosen interval', async () => {
  const client = clientReturning(trend)
  render(<TrendView client={client} period={period} exponents={{ UZS: 0 }} />)

  await userEvent.selectOptions(screen.getByLabelText(strings.reports.interval), 'month')

  await waitFor(() => expect(client.trend).toHaveBeenLastCalledWith(
    expect.objectContaining({ interval: 'month' }),
  ))
})

it('says there is nothing for an empty period', async () => {
  render(<TrendView client={clientReturning({ points: [] })} period={period} exponents={{}} />)

  expect(await screen.findByText(strings.reports.empty)).toBeInTheDocument()
})

// The race the brief names by name: a period change fires a second request while the
// first is still in flight, and the first happens to settle *after* the second. Without
// the effect's `ignore` cleanup, the stale first response would land last and clobber the
// fresh one. Mirrors SummaryView's equivalent test — both requests are held open with
// their own resolver so the test controls the arrival order directly.
it('does not let a stale period response overwrite a newer one', async () => {
  const client = clientReturning(trend)
  const resolvers: Array<(report: TrendReport) => void> = []
  client.trend = vi.fn().mockImplementation(
    () => new Promise<TrendReport>((resolve) => resolvers.push(resolve)),
  )

  const periodA = { from: '2026-07-01', to: '2026-07-31' }
  const periodB = { from: '2026-08-01', to: '2026-08-31' }

  const { rerender } = render(
    <TrendView client={client} period={periodA} exponents={{ UZS: 0 }} chartWidth={320} chartHeight={240} />,
  )
  rerender(<TrendView client={client} period={periodB} exponents={{ UZS: 0 }} chartWidth={320} chartHeight={240} />)

  await waitFor(() => expect(resolvers).toHaveLength(2))

  const staleReport: TrendReport = {
    points: [{ period: '2026-07-01', currency: 'UZS', type: 'expense', amount_minor: 111, amount: '111', count: 1 }],
  }
  const freshReport: TrendReport = {
    points: [{ period: '2026-08-01', currency: 'UZS', type: 'expense', amount_minor: 222, amount: '222', count: 1 }],
  }

  // The second (fresher) request settles first ...
  resolvers[1](freshReport)
  await screen.findByText('222')

  // ... and only afterwards does the first (now-stale) request settle.
  await act(async () => {
    resolvers[0](staleReport)
    await Promise.resolve()
    await Promise.resolve()
  })

  expect(screen.getByText('222')).toBeInTheDocument()
  expect(screen.queryByText('111')).not.toBeInTheDocument()
})

// The rule that outranks everything: money of different currencies is never summed, and
// one currency's figures never land inside another currency's chart. The fixture shares
// a period ('2026-07-01') between UZS and USD on purpose — a naive implementation that
// forgot to filter its rows by currency before building a bucket would let the *other*
// currency's row overwrite or blend into this one's point for that shared period, and the
// resulting figure would visibly diverge from both currencies' real numbers. Asserted in
// both directions: UZS's chart must carry only UZS's figures, USD's only USD's.
it('never lets one currency\'s figures land inside another currency\'s chart', async () => {
  const mixedTrend: TrendReport = {
    points: [
      { period: '2026-07-01', currency: 'UZS', type: 'expense', amount_minor: 150000, amount: '150000', count: 3 },
      { period: '2026-07-02', currency: 'UZS', type: 'expense', amount_minor: 90000, amount: '90000', count: 2 },
      { period: '2026-07-01', currency: 'USD', type: 'expense', amount_minor: 4200, amount: '42.00', count: 1 },
    ],
  }

  render(
    <TrendView
      client={clientReturning(mixedTrend)}
      period={period}
      exponents={{ UZS: 0, USD: 2 }}
      chartWidth={320}
      chartHeight={240}
    />,
  )

  const uzs = await screen.findByTestId('trend-UZS')
  const usd = await screen.findByTestId('trend-USD')

  expect(await within(uzs).findByText('150 000')).toBeInTheDocument()
  expect(await within(uzs).findByText('90 000')).toBeInTheDocument()
  expect(await within(usd).findByText('42.00')).toBeInTheDocument()

  // Neither chart may show a figure that only makes sense as the other currency's row —
  // each checked in the format its *own* exponent would produce, since a leaked raw
  // minor-unit value renders differently depending on which currency's bucket it lands
  // in. UZS catches USD's 4200 rendered at UZS's zero exponent ("4 200"); USD catches
  // UZS's period-two row (90000, which USD has no real entry for at all) rendered at
  // USD's own two-decimal exponent ("900.00") — a phantom bar that would only exist if
  // USD's bucket had silently inherited UZS's period.
  expect(within(uzs).queryByText('4 200')).not.toBeInTheDocument()
  expect(within(usd).queryByText('900.00')).not.toBeInTheDocument()
})
