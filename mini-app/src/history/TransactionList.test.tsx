import { render, screen } from '@testing-library/react'
import { ArrowDownLeft, ArrowUpRight } from 'lucide-react'
import type { ReactElement } from 'react'
import { TransactionList } from './TransactionList'
import type { ApiTransaction } from '../api/types'

function transaction(overrides: Partial<ApiTransaction>): ApiTransaction {
  return {
    id: 1,
    type: 'expense',
    amount_minor: 120000,
    amount: '120000',
    currency: 'UZS',
    occurred_on: '2026-07-15',
    note: null,
    category: { id: 7, name: 'Taksi' },
    user: { id: 1, name: 'Malika Karimova' },
    department: null,
    dimension_values: [],
    created_at: null,
    updated_at: null,
    ...overrides,
  }
}

// Renders a lucide-react icon component in isolation and returns the shape of its
// rendered svg: the <path>/<polyline>/... children only, not the outer <svg>'s own
// width/height/style attributes (those vary with props like `size` and `color`, which
// differ between the income and expense rows). This is a fingerprint of *which* icon
// was rendered, independent of how it happens to be styled where it's used.
function iconShape(icon: ReactElement) {
  const { container, unmount } = render(icon)
  const shape = container.querySelector('svg')?.innerHTML
  unmount()
  return shape
}

it('shows an income row with an up-arrow icon and an expense row with a down-arrow icon', () => {
  const items = [
    transaction({ id: 1, type: 'income' }),
    transaction({ id: 2, type: 'expense' }),
  ]

  render(
    <TransactionList items={items} exponents={{}} hasMore={false} onLoadMore={() => {}} onSelect={() => {}} />,
  )

  const incomeRow = screen.getByTestId('transaction-1')
  const expenseRow = screen.getByTestId('transaction-2')

  const incomeIconShape = incomeRow.querySelector('svg')?.innerHTML
  const expenseIconShape = expenseRow.querySelector('svg')?.innerHTML

  const arrowUpRightShape = iconShape(<ArrowUpRight aria-hidden="true" />)
  const arrowDownLeftShape = iconShape(<ArrowDownLeft aria-hidden="true" />)

  // Sanity check on the reference fixtures themselves: if these two icons ever rendered
  // identically, the assertions below would be meaningless.
  expect(arrowUpRightShape).not.toBe(arrowDownLeftShape)

  // Pins which *specific* icon renders for which transaction type — not just that the
  // two rows differ from each other. A swapped ternary (income -> ArrowDownLeft,
  // expense -> ArrowUpRight) would still make the two rows differ from each other, but
  // would fail these two assertions because each row is checked against its own named
  // reference icon.
  expect(incomeIconShape).toBe(arrowUpRightShape)
  expect(expenseIconShape).toBe(arrowDownLeftShape)
})
