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
