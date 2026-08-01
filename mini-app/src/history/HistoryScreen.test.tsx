import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HistoryScreen } from './HistoryScreen'
import { strings } from '../strings'
import {
  adminUser,
  bootstrapFixture,
  clientWithTransactions,
  managerUser,
  ownerUser,
  staffUser,
} from '../test/fixtures'
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

// The same picker Reports uses (`PeriodPicker`'s custom range, wired to `usePeriod`'s
// `setRange`) is shared here, so a range History's own filters can't reach was the
// review's finding just as much for the ledger as for the reports.
it('sends a chosen custom range to the transaction list', async () => {
  const client = clientWithTransactions([transaction])
  render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={ownerOfTransaction} />)

  await screen.findByTestId('transaction-1')

  await userEvent.click(screen.getByRole('button', { name: strings.reports.customRange }))
  fireEvent.change(screen.getByLabelText(strings.reports.rangeFrom), { target: { value: '2026-01-05' } })
  fireEvent.change(screen.getByLabelText(strings.reports.rangeTo), { target: { value: '2026-01-20' } })

  await waitFor(() =>
    expect(client.listTransactions).toHaveBeenLastCalledWith(
      expect.objectContaining({ from: '2026-01-05', to: '2026-01-20' }),
    ),
  )
})

// The currency control used to accept a new value and send only `{ currency }`, which
// the backend always refuses (a currency change must carry the amount it applies to —
// `tests/Feature/Api/UpdateDeleteTransactionTest.php:105`). Amount editing is out of
// scope, so the fix is removing the control rather than growing the form to satisfy the
// backend's rule.
it('does not offer a currency control the api would always refuse', async () => {
  const client = clientWithTransactions([transaction])
  render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={ownerOfTransaction} />)

  await userEvent.click(await screen.findByTestId('transaction-1'))
  const dialog = screen.getByRole('dialog')

  // Both the history filter bar and the edit form once used the same "Valyuta" label,
  // so the query is scoped to the sheet: the filter's own currency select is expected to
  // stay untouched by this fix.
  expect(within(dialog).queryByLabelText(strings.entry.currency)).not.toBeInTheDocument()

  await userEvent.clear(screen.getByLabelText(strings.entry.note))
  await userEvent.type(screen.getByLabelText(strings.entry.note), 'tuzatildi')
  await userEvent.click(screen.getByRole('button', { name: strings.common.save }))

  const [, body] = (client.updateTransaction as ReturnType<typeof vi.fn>).mock.calls[0] as [number, object]
  expect(body).not.toHaveProperty('currency')
})

// The sheet used to show only the editable fields and the actions; the full record
// (amount, person, department) and the revision count were absent even though amount
// editing being out of scope never meant the amount itself should be hidden.
it('shows the amount, person, department and revision count read-only in the sheet', async () => {
  const client = clientWithTransactions([transaction])
  client.revisions = vi.fn().mockResolvedValue([
    { id: 1, action: 'created', actor: { id: 1, name: 'Malika Karimova' }, snapshot: {}, created_at: '2026-07-15T00:00:00Z' },
    { id: 2, action: 'updated', actor: { id: 1, name: 'Malika Karimova' }, snapshot: {}, created_at: '2026-07-16T00:00:00Z' },
  ])
  render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={ownerOfTransaction} />)

  await userEvent.click(await screen.findByTestId('transaction-1'))
  const dialog = screen.getByRole('dialog')

  expect(within(dialog).getByText(/120 000/)).toBeInTheDocument()
  expect(within(dialog).getByText(transaction.user.name)).toBeInTheDocument()
  expect(within(dialog).getByText(strings.history.noDepartment)).toBeInTheDocument()
  expect(await within(dialog).findByTestId('revision-count')).toHaveTextContent('2')
})

// The review's finding: a failed revisions request used to render the exact same
// placeholder as still-loading, with nothing the user could do about it. It must instead
// read as a failure and offer a retry that actually refetches.
it('offers a retry when the revision count fails to load, and retrying shows the count', async () => {
  const client = clientWithTransactions([transaction])
  client.revisions = vi
    .fn()
    .mockRejectedValueOnce(new Error('network'))
    .mockResolvedValueOnce([
      { id: 1, action: 'created', actor: { id: 1, name: 'Malika Karimova' }, snapshot: {}, created_at: '2026-07-15T00:00:00Z' },
    ])
  render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={ownerOfTransaction} />)

  await userEvent.click(await screen.findByTestId('transaction-1'))
  const dialog = screen.getByRole('dialog')
  const revisionRow = await within(dialog).findByTestId('revision-count')

  expect(await within(revisionRow).findByText(strings.history.revisionsFailed)).toBeInTheDocument()

  await userEvent.click(within(revisionRow).getByRole('button', { name: strings.common.retry }))

  await waitFor(() => expect(revisionRow).toHaveTextContent('1'))
  expect(client.revisions).toHaveBeenCalledTimes(2)
})

describe('edit and delete visibility per role', () => {
  it('shows edit and delete on a staff member’s own row', async () => {
    const client = clientWithTransactions([transaction])
    render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={staffUser} />)

    await userEvent.click(await screen.findByTestId('transaction-1'))

    expect(screen.getByRole('button', { name: strings.common.save })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: strings.common.delete })).toBeInTheDocument()
  })

  it('hides edit and delete from a manager viewing a colleague’s row', async () => {
    const client = clientWithTransactions([transaction])
    render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={managerUser} />)

    await userEvent.click(await screen.findByTestId('transaction-1'))

    expect(screen.queryByRole('button', { name: strings.common.save })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: strings.common.delete })).not.toBeInTheDocument()
  })

  it('hides edit and delete from an owner everywhere, even their own row', async () => {
    const ownedByOwner: ApiTransaction = {
      ...transaction,
      user: { id: ownerUser.id, name: ownerUser.name },
    }
    const client = clientWithTransactions([ownedByOwner])
    render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={ownerUser} />)

    await userEvent.click(await screen.findByTestId('transaction-1'))

    expect(screen.queryByRole('button', { name: strings.common.save })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: strings.common.delete })).not.toBeInTheDocument()
  })

  it('shows edit and delete to an admin on any row', async () => {
    const client = clientWithTransactions([transaction])
    render(<HistoryScreen client={client} bootstrap={bootstrapFixture} user={adminUser} />)

    await userEvent.click(await screen.findByTestId('transaction-1'))

    expect(screen.getByRole('button', { name: strings.common.save })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: strings.common.delete })).toBeInTheDocument()
  })
})
