import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HistoryScreen } from './HistoryScreen'
import { strings } from '../strings'
import { bootstrapFixture, clientWithTransactions, staffUser } from '../test/fixtures'
import type { ApiTransaction } from '../api/types'

const transaction: ApiTransaction = {
  id: 1,
  type: 'expense',
  amount_minor: 120000,
  amount: '120000',
  currency: 'UZS',
  occurred_on: '2026-07-15',
  note: 'eski izoh',
  category: { id: 7, name: 'Taksi' },
  user: { id: staffUser.id, name: staffUser.name },
  department: null,
  dimension_values: [{ dimension_id: 3, dimension_key: 'branch', value_id: 9, value_name: 'Chilonzor' }],
  created_at: null,
  updated_at: null,
}

const ownerOfTransaction = staffUser

it('shows the ledger rows for the active period', async () => {
  const client = clientWithTransactions([transaction])
  render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={ownerOfTransaction} />)

  const row = await screen.findByTestId('transaction-1')
  expect(within(row).getByText('Taksi')).toBeInTheDocument()
})

it('says there is nothing rather than an empty list', async () => {
  const client = clientWithTransactions([])
  render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={ownerOfTransaction} />)

  expect(await screen.findByText(strings.history.empty)).toBeInTheDocument()
})

it('sends only the field that changed', async () => {
  const client = clientWithTransactions([transaction])
  render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={ownerOfTransaction} />)

  await userEvent.click(await screen.findByTestId('transaction-1'))
  await userEvent.clear(screen.getByLabelText(strings.entry.note))
  await userEvent.type(screen.getByLabelText(strings.entry.note), 'tuzatildi')
  await userEvent.click(screen.getByRole('button', { name: strings.common.save }))

  expect(client.updateTransaction).toHaveBeenCalledWith(1, { note: 'tuzatildi' })
})

it('surfaces a refusal from the api rather than guessing', async () => {
  const client = clientWithTransactions([transaction])
  client.updateTransaction = vi.fn().mockRejectedValue({ status: 403, message: "Ruxsat yo'q" })
  render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={ownerOfTransaction} />)

  await userEvent.click(await screen.findByTestId('transaction-1'))
  await userEvent.click(screen.getByRole('button', { name: strings.common.save }))

  expect(await screen.findByText(/Ruxsat yo'q/)).toBeInTheDocument()
})

it('confirms once before deleting', async () => {
  const client = clientWithTransactions([transaction])
  render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={ownerOfTransaction} />)

  await userEvent.click(await screen.findByTestId('transaction-1'))
  await userEvent.click(screen.getByRole('button', { name: strings.common.delete }))
  await userEvent.click(screen.getByRole('button', { name: strings.common.confirm }))

  expect(client.deleteTransaction).toHaveBeenCalledWith(1)
})

it('removes the row once the delete is confirmed', async () => {
  const client = clientWithTransactions([transaction])
  render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={ownerOfTransaction} />)

  await userEvent.click(await screen.findByTestId('transaction-1'))
  await userEvent.click(screen.getByRole('button', { name: strings.common.delete }))
  await userEvent.click(screen.getByRole('button', { name: strings.common.confirm }))

  await waitFor(() => expect(screen.queryByTestId('transaction-1')).not.toBeInTheDocument())
})

// The bracketed `dimension[<key>]` shape and the plain filters both need to reach
// `listTransactions` the way `TransactionListParams` (and, beneath it, `toQueryString`)
// expect — a select whose value never leaves `Filters`'/`HistoryScreen`'s own state
// would pass every test above and still send the API nothing to filter by.
it('reaches the api with the chosen category, currency and dimension filters', async () => {
  const client = clientWithTransactions([transaction])
  render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={ownerOfTransaction} />)

  await screen.findByTestId('transaction-1')

  await userEvent.selectOptions(screen.getByLabelText(strings.history.category), '7')
  await userEvent.selectOptions(screen.getByLabelText(strings.history.currency), 'USD')
  await userEvent.selectOptions(screen.getByLabelText('Filial'), '9')

  await waitFor(() =>
    expect(client.listTransactions).toHaveBeenLastCalledWith(
      expect.objectContaining({ category_id: 7, currency: 'USD', dimension: { branch: 9 } }),
    ),
  )
})
