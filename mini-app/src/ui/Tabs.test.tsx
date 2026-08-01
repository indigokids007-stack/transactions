import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Tabs, type TabItem } from './Tabs'

const items: TabItem[] = [
  { id: 'add', label: 'Kiritish' },
  { id: 'reports', label: 'Hisobotlar' },
  { id: 'history', label: 'Tarix' },
]

it('renders a tablist with one tab per item', () => {
  render(<Tabs value="add" onChange={() => {}} items={items} />)

  expect(screen.getByRole('tablist')).toBeInTheDocument()
  expect(screen.getAllByRole('tab')).toHaveLength(3)
})

it('marks only the active tab as selected', () => {
  render(<Tabs value="reports" onChange={() => {}} items={items} />)

  expect(screen.getByRole('tab', { name: 'Hisobotlar' })).toHaveAttribute('aria-selected', 'true')
  expect(screen.getByRole('tab', { name: 'Kiritish' })).toHaveAttribute('aria-selected', 'false')
  expect(screen.getByRole('tab', { name: 'Tarix' })).toHaveAttribute('aria-selected', 'false')
})

it('reports the clicked tab id and leaves the shown selection to the parent', async () => {
  const onChange = vi.fn()
  const user = userEvent.setup()
  render(<Tabs value="add" onChange={onChange} items={items} />)

  await user.click(screen.getByRole('tab', { name: 'Tarix' }))

  expect(onChange).toHaveBeenCalledWith('history')
  // Tabs is controlled: without the parent applying the new `value`, the click alone
  // does not move the selection.
  expect(screen.getByRole('tab', { name: 'Kiritish' })).toHaveAttribute('aria-selected', 'true')
})
