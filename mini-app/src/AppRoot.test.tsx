import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { AppRoot } from './AppRoot'
import { createClient } from './api/client'

// What this guards against: if `AppRoot` ever created its client from inside its own
// render body instead of at module scope, `useSession`'s effect (which lists `client` in
// its dependency array) would re-run on every unrelated re-render, re-authenticating
// against the live Telegram/API exchange in a loop for as long as the component tree
// stays mounted — not a one-off glitch but a runaway request loop against production.
// The exchange never resolves here: the point of this suite is call counts, not the
// resulting session state, so there is nothing to wait for.
vi.mock('./api/client', () => ({
  createClient: vi.fn(() => ({
    setToken: vi.fn(),
    clearToken: vi.fn(),
    authenticate: vi.fn(() => new Promise(() => {})),
    bootstrap: vi.fn(),
  })),
}))

const createClientMock = vi.mocked(createClient)

// Forces `AppRoot` to re-render several times without remounting it, the way a real
// parent would when unrelated state changes elsewhere in the tree.
function Harness() {
  const [count, setCount] = useState(0)
  return (
    <div>
      <button onClick={() => setCount((n) => n + 1)}>rerender ({count})</button>
      <AppRoot />
    </div>
  )
}

it('creates the api client once and does not re-run the session effect on re-render', async () => {
  const user = userEvent.setup()
  render(<Harness />)

  expect(createClientMock).toHaveBeenCalledOnce()
  const client = createClientMock.mock.results[0]?.value as { authenticate: ReturnType<typeof vi.fn> }
  expect(client.authenticate).toHaveBeenCalledOnce()

  await user.click(screen.getByRole('button', { name: /rerender/ }))
  await user.click(screen.getByRole('button', { name: /rerender/ }))

  // Still exactly one client, still exactly one exchange: `client` kept the same
  // identity across both re-renders, so `useSession`'s effect did not fire again.
  expect(createClientMock).toHaveBeenCalledOnce()
  expect(client.authenticate).toHaveBeenCalledOnce()
})
