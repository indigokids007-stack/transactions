import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Toast } from './Toast'

it('shows the message with no action button when none is given', () => {
  render(<Toast message="Saqlandi." />)

  expect(screen.getByText('Saqlandi.')).toBeInTheDocument()
  expect(screen.queryByRole('button')).not.toBeInTheDocument()
})

it('shows the action and calls onAction when clicked', async () => {
  const onAction = vi.fn()
  const user = userEvent.setup()
  render(<Toast message="Saqlandi." action="Bekor qilish" onAction={onAction} />)

  await user.click(screen.getByRole('button', { name: 'Bekor qilish' }))

  expect(onAction).toHaveBeenCalledOnce()
})

it('never calls onDismiss on its own when none is given', () => {
  vi.useFakeTimers()
  render(<Toast message="Saqlandi." />)

  vi.advanceTimersByTime(10_000)

  vi.useRealTimers()
})

it('calls onDismiss once the duration elapses', () => {
  vi.useFakeTimers()
  const onDismiss = vi.fn()
  render(<Toast message="Saqlandi." onDismiss={onDismiss} duration={4000} />)

  vi.advanceTimersByTime(3999)
  expect(onDismiss).not.toHaveBeenCalled()

  vi.advanceTimersByTime(1)
  expect(onDismiss).toHaveBeenCalledOnce()

  vi.useRealTimers()
})

it('clears its timer on unmount so a stale onDismiss never fires', () => {
  vi.useFakeTimers()
  const onDismiss = vi.fn()
  const { unmount } = render(<Toast message="Saqlandi." onDismiss={onDismiss} duration={4000} />)

  unmount()
  vi.advanceTimersByTime(4000)

  expect(onDismiss).not.toHaveBeenCalled()
  vi.useRealTimers()
})
