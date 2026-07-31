import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { App } from './App'
import { strings } from './strings'
// Reused rather than redefined: `activeSession` and `pendingUser` already carry the
// exact shapes this suite needs (staff permissions on the active user, a pending status
// on the other), per Task 2's fixture kit.
import { activeSession, pendingUser } from './test/fixtures'

it('shows the loading screen and no tabs', () => {
  render(<App session={{ kind: 'loading' }} />)

  expect(screen.getByText(strings.session.loading)).toBeInTheDocument()
  expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
})

it('shows the waiting screen for a pending user and no tabs', () => {
  render(<App session={{ kind: 'pending', user: pendingUser }} />)

  expect(screen.getByText(strings.session.pending)).toBeInTheDocument()
  expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
})

it('shows the refusal message and no tabs', () => {
  render(<App session={{ kind: 'refused', message: 'Registration is closed.' }} />)

  expect(screen.getByText('Registration is closed.')).toBeInTheDocument()
  expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
})

it('shows the error message with a retry action and no tabs', async () => {
  const onRetry = vi.fn()
  const user = userEvent.setup()
  render(<App session={{ kind: 'error', message: 'Server exploded.' }} onRetry={onRetry} />)

  expect(screen.getByText('Server exploded.')).toBeInTheDocument()
  expect(screen.queryByRole('tablist')).not.toBeInTheDocument()

  await user.click(screen.getByRole('button', { name: strings.common.retry }))
  expect(onRetry).toHaveBeenCalledOnce()
})

it('renders the three tabs for an active user', () => {
  render(<App session={activeSession} />)

  expect(screen.getByRole('tab', { name: strings.tabs.add })).toBeInTheDocument()
  expect(screen.getByRole('tab', { name: strings.tabs.reports })).toBeInTheDocument()
  expect(screen.getByRole('tab', { name: strings.tabs.history })).toBeInTheDocument()
})

// This only proves no tab is ever named `strings.tabs.staff` — it says nothing about
// permission gating. The staff comparison itself lives inside the Reports tab, gated by
// `permissions.can_see_all`/`can_manage`, and arrives with Task 8.
it('hides the staff comparison for a user who cannot see others', () => {
  render(<App session={activeSession} />)

  expect(screen.queryByRole('tab', { name: strings.tabs.staff })).not.toBeInTheDocument()
})

it('has an aria-controls target that resolves to an element in the document, for every tab', () => {
  render(<App session={activeSession} />)

  const tabs = screen.getAllByRole('tab')
  expect(tabs).toHaveLength(3)

  for (const tab of tabs) {
    const controlsId = tab.getAttribute('aria-controls')
    expect(controlsId).toBeTruthy()
    expect(document.getElementById(controlsId as string)).not.toBeNull()
  }
})

it('keeps a tab mounted (not torn down) so its state survives switching away and back', async () => {
  const user = userEvent.setup()
  render(<App session={activeSession} />)

  const addInput = screen.getByRole('textbox', { name: strings.tabs.add })
  await user.type(addInput, 'tuzatildi')
  expect(addInput).toHaveValue('tuzatildi')

  await user.click(screen.getByRole('tab', { name: strings.tabs.history }))
  await user.click(screen.getByRole('tab', { name: strings.tabs.add }))

  expect(screen.getByRole('textbox', { name: strings.tabs.add })).toHaveValue('tuzatildi')
})
