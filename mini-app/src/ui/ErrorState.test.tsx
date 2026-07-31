import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ErrorState } from './ErrorState'

it('shows the message with no action button when none is given', () => {
  render(<ErrorState message="Something broke." />)

  expect(screen.getByText('Something broke.')).toBeInTheDocument()
  expect(screen.queryByRole('button')).not.toBeInTheDocument()
})

it('shows the action button and calls onAction when clicked', async () => {
  const onAction = vi.fn()
  const user = userEvent.setup()
  render(<ErrorState message="Something broke." actionLabel="Qaytadan urinish" onAction={onAction} />)

  await user.click(screen.getByRole('button', { name: 'Qaytadan urinish' }))

  expect(onAction).toHaveBeenCalledOnce()
})
