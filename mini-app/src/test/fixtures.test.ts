import {
  activeSession,
  bootstrapFixture,
  clientReturning,
  clientStub,
  clientWithTransactions,
  managerUser,
  ownerUser,
  pendingUser,
  staffUser,
} from './fixtures'
import type { ApiTransaction, SummaryReport } from '../api/types'

describe('clientStub', () => {
  it('resolves every method to an empty successful shape', async () => {
    const client = clientStub()

    await expect(client.deleteTransaction(1)).resolves.toBeUndefined()
    await expect(client.listTransactions({})).resolves.toEqual({ data: [], meta: { next_cursor: null } })
    await expect(client.createTransaction({}, 'k')).resolves.toEqual({ data: { id: 1 } })
    await expect(client.summary({})).resolves.toEqual({ totals: [], groups: [] })
    await expect(client.trend({})).resolves.toEqual({ points: [] })
  })

  it('lets a caller override one method while keeping the rest of the defaults', async () => {
    const listTransactions = vi.fn().mockResolvedValue({ data: [{ id: 5 }], meta: { next_cursor: 'x' } })
    const client = clientStub({ listTransactions })

    await expect(client.listTransactions({})).resolves.toEqual({
      data: [{ id: 5 }],
      meta: { next_cursor: 'x' },
    })
    await expect(client.summary({})).resolves.toEqual({ totals: [], groups: [] })
  })
})

describe('clientReturning', () => {
  it('resolves both summary and trend to the given report', async () => {
    const report: SummaryReport = {
      totals: [{ currency: 'UZS', type: 'expense', amount_minor: 1, amount: '1', count: 1 }],
      groups: [],
    }
    const client = clientReturning(report)

    await expect(client.summary({})).resolves.toBe(report)
    await expect(client.trend({})).resolves.toBe(report)
  })
})

describe('clientWithTransactions', () => {
  it('lists the given items and keeps update/delete as inspectable spies', async () => {
    const item = { id: 42 } as unknown as ApiTransaction
    const client = clientWithTransactions([item])

    await expect(client.listTransactions({})).resolves.toEqual({ data: [item], meta: { next_cursor: null } })

    await client.updateTransaction(42, {})
    await client.deleteTransaction(42)

    expect(client.updateTransaction).toHaveBeenCalledWith(42, {})
    expect(client.deleteTransaction).toHaveBeenCalledWith(42)
  })
})

describe('user fixtures', () => {
  it('gives each role its documented permissions', () => {
    expect(staffUser.permissions).toEqual({ can_see_all: false, can_manage: false })
    expect(managerUser.permissions).toEqual({ can_see_all: false, can_manage: true })
    expect(ownerUser.permissions).toEqual({ can_see_all: true, can_manage: true })
    expect(pendingUser.status).toBe('pending')
  })
})

describe('bootstrapFixture', () => {
  it('carries the one category, dimension and value described in the plan', () => {
    expect(bootstrapFixture.categories).toEqual([{ id: 7, name: 'Taksi', applies_to: 'expense', children: [] }])
    expect(bootstrapFixture.dimensions).toHaveLength(1)
    expect(bootstrapFixture.dimensions[0]).toMatchObject({ id: 3, key: 'branch', is_required: true })
    expect(bootstrapFixture.dimensions[0].values).toEqual([{ id: 9, name: 'Chilonzor' }])
    expect(bootstrapFixture.currencies).toEqual({ UZS: 0, USD: 2 })
    expect(bootstrapFixture.defaults).toMatchObject({ category_id: 7, currency: 'UZS' })
  })
})

describe('activeSession', () => {
  it('pairs the staff user with the bootstrap fixture', () => {
    expect(activeSession).toEqual({ kind: 'active', user: staffUser, bootstrap: bootstrapFixture })
  })
})
