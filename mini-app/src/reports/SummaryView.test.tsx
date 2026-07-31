import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { SummaryView } from './SummaryView'
import { strings } from '../strings'
import { clientReturning } from '../test/fixtures'
import type { SummaryReport } from '../api/types'

const period = { from: '2026-07-01', to: '2026-07-31' }

const report: SummaryReport = {
  totals: [
    { currency: 'UZS', type: 'expense', amount_minor: 320000, amount: '320000', count: 4 },
    { currency: 'USD', type: 'expense', amount_minor: 1000, amount: '10.00', count: 1 },
  ],
  groups: [
    { key: '7', label: 'Taksi', currency: 'UZS', type: 'expense', amount_minor: 120000, amount: '120000', count: 2 },
    { key: '8', label: 'Ofis', currency: 'UZS', type: 'expense', amount_minor: 200000, amount: '200000', count: 2 },
    { key: '7', label: 'Taksi', currency: 'USD', type: 'expense', amount_minor: 1000, amount: '10.00', count: 1 },
  ],
}

it('renders one section per currency and never merges their totals', async () => {
  render(<SummaryView client={clientReturning(report)} period={period} exponents={{ UZS: 0, USD: 2 }} />)

  const uzs = await screen.findByTestId('currency-UZS')
  const usd = await screen.findByTestId('currency-USD')

  expect(within(uzs).getByText(/320 000/)).toBeInTheDocument()
  expect(within(usd).getByText(/10\.00/)).toBeInTheDocument()
  expect(screen.queryByText(/321 000/)).not.toBeInTheDocument()
})

// The chart is the part most likely to look "done" while actually rendering nothing: a
// pie with no visible slices still leaves the totals assertion above green. This proves
// each currency's own group rows actually reached the chart, not just the totals line —
// asserting on the legend text rather than SVG geometry, per the brief's guidance for
// Recharts under jsdom.
it('draws each currency chart from its own group rows, not the other currency\'s', async () => {
  render(
    <SummaryView
      client={clientReturning(report)}
      period={period}
      exponents={{ UZS: 0, USD: 2 }}
      chartWidth={320}
      chartHeight={240}
    />,
  )

  const uzs = await screen.findByTestId('currency-UZS')
  const usd = await screen.findByTestId('currency-USD')

  // The legend payload reaches the DOM through recharts' own internal store, one tick
  // after the surrounding render — a plain `getByText` here would pass or fail on
  // timing alone, not on which rows the chart was given.
  expect(await within(uzs).findByText('Taksi')).toBeInTheDocument()
  expect(await within(uzs).findByText('Ofis')).toBeInTheDocument()
  expect(await within(usd).findByText('Taksi')).toBeInTheDocument()
  expect(within(usd).queryByText('Ofis')).not.toBeInTheDocument()
})

it('asks the api to group by a dimension when one is chosen', async () => {
  const client = clientReturning(report)
  render(
    <SummaryView
      client={client}
      period={period}
      exponents={{ UZS: 0 }}
      dimensions={[{ id: 3, key: 'branch', name: 'Filial', is_required: false, values: [] }]}
    />,
  )

  await userEvent.selectOptions(screen.getByLabelText(strings.reports.groupBy), 'dimension:branch')

  await waitFor(() =>
    expect(client.summary).toHaveBeenLastCalledWith(expect.objectContaining({ group_by: 'dimension:branch' })),
  )
})

it('says there is nothing rather than drawing an empty chart', async () => {
  render(<SummaryView client={clientReturning({ totals: [], groups: [] })} period={period} exponents={{}} />)

  expect(await screen.findByText(strings.reports.empty)).toBeInTheDocument()
})

it('refetches when the period changes', async () => {
  const client = clientReturning(report)
  const { rerender } = render(<SummaryView client={client} period={period} exponents={{ UZS: 0, USD: 2 }} />)

  await screen.findByTestId('currency-UZS')

  const nextPeriod = { from: '2026-08-01', to: '2026-08-31' }
  rerender(<SummaryView client={client} period={nextPeriod} exponents={{ UZS: 0, USD: 2 }} />)

  await waitFor(() =>
    expect(client.summary).toHaveBeenLastCalledWith(expect.objectContaining(nextPeriod)),
  )
})
