import { render, screen } from '@testing-library/react'
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

  expect(incomeRow.querySelector('svg')).toBeInTheDocument()
  expect(expenseRow.querySelector('svg')).toBeInTheDocument()
})
