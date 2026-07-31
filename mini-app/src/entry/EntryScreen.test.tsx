import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { EntryScreen } from './EntryScreen'
import { strings } from '../strings'
import { bootstrapFixture, clientStub } from '../test/fixtures'

async function enterValidTransaction() {
  await userEvent.click(screen.getByRole('button', { name: '1' }))
  await userEvent.click(screen.getByRole('button', { name: strings.entry.details }))
  await userEvent.selectOptions(screen.getByLabelText('Filial'), '9')
  await userEvent.click(screen.getByRole('button', { name: strings.entry.save }))
}

it('names the required dimension instead of saving', async () => {
  const client = clientStub()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} />)

  await userEvent.click(screen.getByRole('button', { name: '1' }))
  await userEvent.click(screen.getByRole('button', { name: strings.entry.save }))

  expect(screen.getByText(/Filial/)).toBeInTheDocument()
  expect(client.createTransaction).not.toHaveBeenCalled()
})

it('offers undo after a save and deletes on tap', async () => {
  const client = clientStub()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} />)

  await enterValidTransaction()
  await userEvent.click(await screen.findByRole('button', { name: strings.entry.undo }))

  expect(client.deleteTransaction).toHaveBeenCalledWith(1)
})

it('reloads the reference data when the api rejects a stale category', async () => {
  const client = clientStub()
  client.createTransaction = vi.fn().mockRejectedValue({
    status: 422,
    errors: { category_id: ['The selected category_id is invalid.'] },
  })
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} />)

  await enterValidTransaction()

  expect(await screen.findByText(strings.entry.referenceChanged)).toBeInTheDocument()
  expect(client.bootstrap).toHaveBeenCalled()
})
