import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { EntryScreen } from './EntryScreen'
import { strings } from '../strings'
import { bootstrapFixture, clientStub } from '../test/fixtures'

async function fillValidAmountAndDimension() {
  await userEvent.click(screen.getByRole('button', { name: '1' }))
  await userEvent.click(screen.getByRole('button', { name: strings.entry.details }))
  await userEvent.selectOptions(screen.getByLabelText('Filial'), '9')
}

async function enterValidTransaction() {
  await fillValidAmountAndDimension()
  await userEvent.click(screen.getByRole('button', { name: strings.entry.save }))
}

// The grammar `parseAmount` accepts is reachable only through a real text input; typing
// a magnitude word and a comma-separated group prove that reach, not just the digit
// keypad (which never offered a way to type either).
it('saves a magnitude word typed into the amount field as its multiplied value', async () => {
  const client = clientStub()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} />)

  await userEvent.type(screen.getByLabelText(strings.entry.amount), '30 ming')
  await userEvent.selectOptions(screen.getByLabelText('Filial'), '9')
  await userEvent.click(screen.getByRole('button', { name: strings.entry.save }))

  expect(client.createTransaction).toHaveBeenCalledWith(
    expect.objectContaining({ amount: '30000' }),
    expect.any(String),
  )
})

it('saves a comma-separated amount typed into the amount field padded to its group', async () => {
  const client = clientStub()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} />)

  await userEvent.type(screen.getByLabelText(strings.entry.amount), '12,50')
  await userEvent.selectOptions(screen.getByLabelText('Filial'), '9')
  await userEvent.click(screen.getByRole('button', { name: strings.entry.save }))

  expect(client.createTransaction).toHaveBeenCalledWith(
    expect.objectContaining({ amount: '12500' }),
    expect.any(String),
  )
})

it('shows a hint instead of saving when the amount does not parse', async () => {
  const client = clientStub()
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} />)

  await userEvent.type(screen.getByLabelText(strings.entry.amount), '100 000')

  expect(await screen.findByText(strings.entry.invalidAmount)).toBeInTheDocument()
  expect(client.createTransaction).not.toHaveBeenCalled()
})

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
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} />)

  await enterValidTransaction()

  expect(await screen.findByText('Note is too long.')).toBeInTheDocument()
})

it('shows a generic failure notice when a non-422 save fails', async () => {
  const client = clientStub()
  client.createTransaction = vi.fn().mockRejectedValue(new Error('network exploded'))
  render(<EntryScreen bootstrap={bootstrapFixture} client={client} />)

  await enterValidTransaction()

  expect(await screen.findByText(strings.entry.saveFailed)).toBeInTheDocument()
})

describe('MainButton gating while another tab is showing', () => {
  afterEach(() => {
    delete (window as { Telegram?: unknown }).Telegram
  })

  function stubMainButton() {
    const mainButton = {
      text: '',
      isVisible: false,
      isActive: true,
      setText: vi.fn(),
      show: vi.fn(),
      hide: vi.fn(),
      enable: vi.fn(),
      disable: vi.fn(),
      onClick: vi.fn(),
      offClick: vi.fn(),
    }
    window.Telegram = {
      WebApp: {
        initData: '',
        colorScheme: 'light',
        themeParams: {},
        MainButton: mainButton,
        ready: vi.fn(),
        expand: vi.fn(),
        close: vi.fn(),
        onEvent: vi.fn(),
        offEvent: vi.fn(),
      },
    }
    return mainButton
  }

  it('never binds the click handler while the screen is inactive', () => {
    const mainButton = stubMainButton()

    render(<EntryScreen bootstrap={bootstrapFixture} client={clientStub()} active={false} />)

    expect(mainButton.onClick).not.toHaveBeenCalled()
  })

  it('unbinds the handler the moment the screen becomes inactive', () => {
    const mainButton = stubMainButton()

    const { rerender } = render(
      <EntryScreen bootstrap={bootstrapFixture} client={clientStub()} active={true} />,
    )
    expect(mainButton.onClick).toHaveBeenCalledTimes(1)
    const boundHandler = mainButton.onClick.mock.calls[0][0]

    rerender(<EntryScreen bootstrap={bootstrapFixture} client={clientStub()} active={false} />)

    expect(mainButton.offClick).toHaveBeenCalledWith(boundHandler)
  })

  it('hides the button while inactive and shows it again when the tab returns', () => {
    const mainButton = stubMainButton()

    const { rerender } = render(
      <EntryScreen bootstrap={bootstrapFixture} client={clientStub()} active={true} />,
    )
    expect(mainButton.show).toHaveBeenCalled()
    expect(mainButton.hide).not.toHaveBeenCalled()

    rerender(<EntryScreen bootstrap={bootstrapFixture} client={clientStub()} active={false} />)
    expect(mainButton.hide).toHaveBeenCalled()

    const showCallsBeforeReturn = mainButton.show.mock.calls.length
    rerender(<EntryScreen bootstrap={bootstrapFixture} client={clientStub()} active={true} />)
    expect(mainButton.show.mock.calls.length).toBeGreaterThan(showCallsBeforeReturn)
  })

  it('disables the button while inactive even though the form is otherwise ready to save', async () => {
    const mainButton = stubMainButton()

    const { rerender } = render(
      <EntryScreen bootstrap={bootstrapFixture} client={clientStub()} active={true} />,
    )
    await fillValidAmountAndDimension()
    expect(mainButton.enable).toHaveBeenCalled()

    // `disable` was already called once during the initial empty-amount render, before
    // canSave became true — clear that history so the assertion below can only pass
    // because of the inactive transition, not an earlier, unrelated call.
    mainButton.disable.mockClear()

    rerender(<EntryScreen bootstrap={bootstrapFixture} client={clientStub()} active={false} />)

    expect(mainButton.disable).toHaveBeenCalled()
  })
})
