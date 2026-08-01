import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { EntryScreen } from './EntryScreen'
import { strings } from '../strings'
import { bootstrapFixture, clientStub } from '../test/fixtures'

// `EntryScreen` no longer owns a save button itself — the bottom tab bar's center
// button becomes Save once the form is valid (`App.tsx`), reached here through
// `onSaveStateChange`. This captures the latest `canSave`/`save` it reports, the same
// pair `App.tsx` wires into `Tabs`' `addAction`, so a test can trigger a save (or assert
// it's withheld) without a DOM button to click.
function captureSaveState() {
  const state: { canSave: boolean; save: () => void } = { canSave: false, save: () => {} }
  const onSaveStateChange = (canSave: boolean, save: () => void) => {
    state.canSave = canSave
    state.save = save
  }
  return { state, onSaveStateChange }
}

async function fillValidAmountAndDimension() {
  await userEvent.click(screen.getByRole('button', { name: '1' }))
  await userEvent.click(screen.getByRole('button', { name: strings.entry.details }))
  await userEvent.selectOptions(screen.getByLabelText('Filial'), '9')
}

// The grammar `parseAmount` accepts is reachable only through a real text input; typing
// a magnitude word and a comma-separated group prove that reach, not just the digit
// keypad (which never offered a way to type either).
it('saves a magnitude word typed into the amount field as its multiplied value', async () => {
  const client = clientStub()
  const capture = captureSaveState()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} onSaveStateChange={capture.onSaveStateChange} />)

  await userEvent.type(screen.getByLabelText(strings.entry.amount), '30 ming')
  await userEvent.click(screen.getByRole('button', { name: strings.entry.details }))
  await userEvent.selectOptions(screen.getByLabelText('Filial'), '9')
  capture.state.save()

  expect(client.createTransaction).toHaveBeenCalledWith(
    expect.objectContaining({ amount: '30000' }),
    expect.any(String),
  )
})

it('saves a comma-separated amount typed into the amount field padded to its group', async () => {
  const client = clientStub()
  const capture = captureSaveState()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} onSaveStateChange={capture.onSaveStateChange} />)

  await userEvent.type(screen.getByLabelText(strings.entry.amount), '12,50')
  await userEvent.click(screen.getByRole('button', { name: strings.entry.details }))
  await userEvent.selectOptions(screen.getByLabelText('Filial'), '9')
  capture.state.save()

  expect(client.createTransaction).toHaveBeenCalledWith(
    expect.objectContaining({ amount: '12500' }),
    expect.any(String),
  )
})

it('shows a hint instead of saving when the amount does not parse', async () => {
  const client = clientStub()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} />)

  await userEvent.type(screen.getByLabelText(strings.entry.amount), 'abc')

  expect(await screen.findByText(strings.entry.invalidAmount)).toBeInTheDocument()
  expect(client.createTransaction).not.toHaveBeenCalled()
})

// The amount field is now a space-grouped display: `event.target.value` at each
// keystroke already carries the previous render's grouping spaces, so this asserts on
// what reaches `setAmount` (via the saved payload), not on the field's DOM value.
it('saves a space-grouped digit amount as its plain digit value', async () => {
  const client = clientStub()
  const capture = captureSaveState()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} onSaveStateChange={capture.onSaveStateChange} />)

  await userEvent.type(screen.getByLabelText(strings.entry.amount), '120000')
  await userEvent.click(screen.getByRole('button', { name: strings.entry.details }))
  await userEvent.selectOptions(screen.getByLabelText('Filial'), '9')
  capture.state.save()

  expect(client.createTransaction).toHaveBeenCalledWith(
    expect.objectContaining({ amount: '120000' }),
    expect.any(String),
  )
})

// The forced-open sheet was replaced by a coral dot on the Batafsil trigger; the reported
// `canSave` (unchanged, from `useEntryForm`) is still the one gate, now read by `App.tsx`
// instead of a button's own `disabled` attribute.
it('keeps canSave false and marks Batafsil while a required dimension is unanswered', async () => {
  const client = clientStub()
  const capture = captureSaveState()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} onSaveStateChange={capture.onSaveStateChange} />)

  await userEvent.click(screen.getByRole('button', { name: '1' }))

  expect(capture.state.canSave).toBe(false)

  capture.state.save()
  expect(client.createTransaction).not.toHaveBeenCalled()
})

it('offers undo after a save and deletes on tap', async () => {
  const client = clientStub()
  const capture = captureSaveState()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} onSaveStateChange={capture.onSaveStateChange} />)

  await fillValidAmountAndDimension()
  capture.state.save()

  await userEvent.click(await screen.findByRole('button', { name: strings.entry.undo }))

  expect(client.deleteTransaction).toHaveBeenCalledWith(1)
})

it('reloads the reference data when the api rejects a stale category', async () => {
  const client = clientStub()
  client.createTransaction = vi.fn().mockRejectedValue({
    status: 422,
    errors: { category_id: ['The selected category_id is invalid.'] },
  })
  const capture = captureSaveState()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} onSaveStateChange={capture.onSaveStateChange} />)

  await fillValidAmountAndDimension()
  capture.state.save()

  expect(await screen.findByText(strings.entry.referenceChanged)).toBeInTheDocument()
  expect(client.bootstrap).toHaveBeenCalled()
  // The notice above is the whole message; the raw backend string must not also render,
  // or the screen shows the same failure twice — once translated, once not.
  expect(screen.queryByText('The selected category_id is invalid.')).not.toBeInTheDocument()
})

it('shows a 422 field error inline instead of doing nothing', async () => {
  const client = clientStub()
  client.createTransaction = vi.fn().mockRejectedValue({
    status: 422,
    errors: { note: ['Note is too long.'] },
  })
  const capture = captureSaveState()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} onSaveStateChange={capture.onSaveStateChange} />)

  await fillValidAmountAndDimension()
  capture.state.save()

  expect(await screen.findByText('Note is too long.')).toBeInTheDocument()
})

it('shows a generic failure notice when a non-422 save fails', async () => {
  const client = clientStub()
  client.createTransaction = vi.fn().mockRejectedValue(new Error('network exploded'))
  const capture = captureSaveState()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} onSaveStateChange={capture.onSaveStateChange} />)

  await fillValidAmountAndDimension()
  capture.state.save()

  expect(await screen.findByText(strings.entry.saveFailed)).toBeInTheDocument()
})

// `onSaveStateChange` reports a *stable* trigger (see `EntryScreen`'s doc comment) — this
// pins that stability, since a save button implemented as a fresh closure per keystroke
// would make `App.tsx`'s `useEffect` (and its `Tabs` re-render) fire on every keystroke
// instead of only when `canSave` flips.
it('reports the same save function across renders that do not change canSave', async () => {
  const capture = captureSaveState()
  render(<EntryScreen bootstrap={bootstrapFixture} client={clientStub()} onSaveStateChange={capture.onSaveStateChange} />)

  const firstSave = capture.state.save
  // Typing a digit re-renders the form (a new `amountInput`) without making `canSave`
  // true (no dimension chosen yet) — the case that would leak a fresh closure per
  // keystroke if `save` weren't wrapped behind a stable ref.
  await userEvent.click(screen.getByRole('button', { name: '1' }))

  expect(capture.state.canSave).toBe(false)
  expect(capture.state.save).toBe(firstSave)
})
