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
  render(<TrendView client={clientReturning(trend)} period={period} />)

  expect(await screen.findByTestId('trend-UZS')).toBeInTheDocument()
  expect(await screen.findByTestId('trend-USD')).toBeInTheDocument()
})

it('asks the api for the chosen interval', async () => {
  const client = clientReturning(trend)
  render(<TrendView client={client} period={period} />)

  await userEvent.selectOptions(screen.getByLabelText(strings.reports.interval), 'month')

  await waitFor(() => expect(client.trend).toHaveBeenLastCalledWith(
    expect.objectContaining({ interval: 'month' }),
  ))
})

it('says there is nothing for an empty period', async () => {
  render(<TrendView client={clientReturning({ points: [] })} period={period} />)

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
    <TrendView client={client} period={periodA} chartWidth={320} chartHeight={240} />,
  )
  rerender(<TrendView client={client} period={periodB} chartWidth={320} chartHeight={240} />)

  await waitFor(() => expect(resolvers).toHaveLength(2))

  // 111000/222000 rather than 111/222: UZS's bar label is scaled to thousands, and these
  // values are chosen so the scaled label ("111"/"222") is the same text this test always
  // asserted on — the race being tested is unrelated to the scaling feature.
  const staleReport: TrendReport = {
    points: [{ period: '2026-07-01', currency: 'UZS', type: 'expense', amount_minor: 111000, amount: '111000', count: 1 }],
  }
  const freshReport: TrendReport = {
    points: [{ period: '2026-08-01', currency: 'UZS', type: 'expense', amount_minor: 222000, amount: '222000', count: 1 }],
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
      chartWidth={320}
      chartHeight={240}
    />,
  )

  const uzs = await screen.findByTestId('trend-UZS')
  const usd = await screen.findByTestId('trend-USD')

  // UZS is the one currency scaled to thousands (150000 -> "150", 90000 -> "90"); USD
  // keeps its own two-decimal figure unscaled.
  expect(await within(uzs).findByText('150')).toBeInTheDocument()
  expect(await within(uzs).findByText('90')).toBeInTheDocument()
  expect(await within(usd).findByText('42.00')).toBeInTheDocument()

  // The raw, unscaled UZS figures must not appear either — proving the scaling actually
  // ran rather than being silently skipped.
  expect(within(uzs).queryByText('150 000')).not.toBeInTheDocument()
  expect(within(uzs).queryByText('90 000')).not.toBeInTheDocument()

  // Neither chart may show a figure that only makes sense as the other currency's row.
  // UZS catches USD's own unscaled two-decimal label ("42.00") — UZS never shows a decimal
  // point, so its presence would mean USD's row landed in the wrong bucket. USD catches
  // both of UZS's forms: the scaled ("150"/"90") and the raw ("150 000"/"90 000") — a
  // phantom bar that would only exist if USD's bucket had silently inherited UZS's period.
  expect(within(uzs).queryByText('42.00')).not.toBeInTheDocument()
  expect(within(usd).queryByText('150')).not.toBeInTheDocument()
  expect(within(usd).queryByText('90')).not.toBeInTheDocument()
  expect(within(usd).queryByText('150 000')).not.toBeInTheDocument()
  expect(within(usd).queryByText('900.00')).not.toBeInTheDocument()
})

// The review's exact scenario, mirroring `SummaryView`'s equivalent test, now under UZS's
// thousands scaling: `amount_minor` is deliberately the value `JSON.parse` would round a
// too-large sum down to, standing in for what a real response already looks like on
// arrival; `amount` carries the true figure. A plain division by 1000 would wash out a
// one-unit gap between the two, so the exact amount here (901 past the rounded
// `amount_minor`) is chosen to still land on a different scaled figure either way: the
// lossy number divides evenly to "10000000000000", the exact string rounds up to
// "10000000000001". If `TrendSection`'s bar label ever went back to reading the raw
// `income` number instead of `incomeAmount`, this label would show the lossy figure
// instead. (Not asserting the lossy figure's absence: at this magnitude the Y axis's own
// auto-generated gridlines legitimately land on nearby round numbers once scaled, so a
// bare "10000000000000" can correctly appear as an axis tick in the same chart — the
// bar's own label is what this test is about, and its presence is the whole proof.)
it('labels a bar above Number.MAX_SAFE_INTEGER exactly, from the string amount, even scaled', async () => {
  const bigTrend: TrendReport = {
    points: [
      { period: '2026-07-01', currency: 'UZS', type: 'expense', amount_minor: 10000000000000000, amount: '10000000000000901', count: 11 },
    ],
  }

  render(<TrendView client={clientReturning(bigTrend)} period={period} chartWidth={320} chartHeight={240} />)

  const uzs = await screen.findByTestId('trend-UZS')
  expect(await within(uzs).findByText('10000000000001')).toBeInTheDocument()
})

// The review's finding: a failed request used to render a dead end with no action.
it('offers a retry action when the request fails, and retrying refetches', async () => {
  const client = clientReturning(trend)
  client.trend = vi
    .fn()
    .mockRejectedValueOnce({ status: 500, message: 'Server exploded.' })
    .mockResolvedValueOnce(trend)

  render(<TrendView client={client} period={period} />)

  expect(await screen.findByText(strings.reports.loadFailed)).toBeInTheDocument()
  await userEvent.click(screen.getByRole('button', { name: strings.common.retry }))

  await screen.findByTestId('trend-UZS')
  expect(client.trend).toHaveBeenCalledTimes(2)
})

// The other half of the same finding: a 429 must read as "too many requests, try again
// in a moment", not the generic failure message.
it('shows a specific message for a 429, not the generic failure', async () => {
  const client = clientReturning(trend)
  client.trend = vi.fn().mockRejectedValue({ status: 429, message: 'Too Many Requests' })

  render(<TrendView client={client} period={period} />)

  expect(await screen.findByText(strings.reports.rateLimited)).toBeInTheDocument()
  expect(screen.queryByText(strings.reports.loadFailed)).not.toBeInTheDocument()
})

// The Y axis's own gridline labels, not the bar labels above — recharts generates its own
// "nice" tick values from the data range, so this reads whatever it chose for a chart
// whose UZS data tops out at 1 000 000, and confirms the axis reads in thousands too, not
// only the bars. Reads the tick `<tspan>`s directly rather than through
// `getByText`/`findByText`: Testing Library's default text query does not reliably match
// text nested in an SVG `<text><tspan>` pair the way it matches a plain element, which
// this test hit as a false failure before switching to a direct DOM read.
it('scales the y axis to thousands for uzs, leaving usd alone', async () => {
  const scaledAxisTrend: TrendReport = {
    points: [
      { period: '2026-07-01', currency: 'UZS', type: 'expense', amount_minor: 1000000, amount: '1000000', count: 1 },
      { period: '2026-07-02', currency: 'USD', type: 'expense', amount_minor: 100000, amount: '1000.00', count: 1 },
    ],
  }

  render(
    <TrendView client={clientReturning(scaledAxisTrend)} period={period} chartWidth={320} chartHeight={240} />,
  )

  const uzs = await screen.findByTestId('trend-UZS')
  const usd = await screen.findByTestId('trend-USD')

  function yAxisTicks(section: HTMLElement): string[] {
    return [...section.querySelectorAll('.recharts-yAxis-tick-labels tspan')].map((el) => el.textContent ?? '')
  }

  // Recharts picks a "nice" round number of ticks between 0 and the series' own max; for a
  // single UZS point at 1 000 000 that is 0/250 000/500 000/750 000/1 000 000 — scaled,
  // 0/250/500/750/1000. USD, left unscaled, keeps its own default tick reading the raw
  // number of minor units — its top tick is "100000" for the same underlying magnitude,
  // proving the scaling is UZS-only, not applied to every axis.
  expect(yAxisTicks(uzs)).toEqual(['0', '250', '500', '750', '1000'])
  expect(yAxisTicks(usd)).toContain('100000')
})
