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
    const onClick = vi.fn()
    const offClick = vi.fn()
    window.Telegram = {
      WebApp: {
        initData: '',
        colorScheme: 'light',
        themeParams: {},
        MainButton: {
          text: '',
          isVisible: false,
          isActive: true,
          setText: vi.fn(),
          show: vi.fn(),
          hide: vi.fn(),
          enable: vi.fn(),
          disable: vi.fn(),
          onClick,
          offClick,
        },
        ready: vi.fn(),
        expand: vi.fn(),
        close: vi.fn(),
        onEvent: vi.fn(),
        offEvent: vi.fn(),
      },
    }
    return { onClick, offClick }
  }

  it('never binds the click handler while the screen is inactive', () => {
    const { onClick } = stubMainButton()

    render(<EntryScreen bootstrap={bootstrapFixture} client={clientStub()} active={false} />)

    expect(onClick).not.toHaveBeenCalled()
  })

  it('unbinds the handler the moment the screen becomes inactive', () => {
    const { onClick, offClick } = stubMainButton()

    const { rerender } = render(
      <EntryScreen bootstrap={bootstrapFixture} client={clientStub()} active={true} />,
    )
    expect(onClick).toHaveBeenCalledTimes(1)
    const boundHandler = onClick.mock.calls[0][0]

    rerender(<EntryScreen bootstrap={bootstrapFixture} client={clientStub()} active={false} />)

    expect(offClick).toHaveBeenCalledWith(boundHandler)
  })
})
