import { act, renderHook, waitFor } from '@testing-library/react'
import { useTransactions } from './useTransactions'
import type { ApiTransaction, CursorPage } from '../api/types'

function transaction(id: number): ApiTransaction {
  return {
    id,
    type: 'expense',
    amount_minor: 1000,
    amount: '1000',
    currency: 'UZS',
    occurred_on: '2026-07-01',
    note: null,
    category: { id: 1, name: 'Taksi' },
    user: { id: 1, name: 'Malika Karimova' },
    department: null,
    dimension_values: [],
    created_at: null,
    updated_at: null,
  }
}

it('appends the next page and stops at the end', async () => {
  const client = {
    listTransactions: vi
      .fn()
      .mockResolvedValueOnce({ data: [transaction(1)], meta: { next_cursor: 'abc' } })
      .mockResolvedValueOnce({ data: [transaction(2)], meta: { next_cursor: null } }),
  }
  const { result } = renderHook(() => useTransactions(client, {}))

  await waitFor(() => expect(result.current.items).toHaveLength(1))
  await act(async () => {
    await result.current.loadMore()
  })

  expect(result.current.items.map((item) => item.id)).toEqual([1, 2])
  expect(result.current.hasMore).toBe(false)
})

// `loadMore` must not fire a third request once the API has said there is nothing
// further: nothing in the pagination test above proves this, since it never calls
// `loadMore` again after `hasMore` goes false.
it('does not fetch again once the api has said there is no next page', async () => {
  const listTransactions = vi
    .fn()
    .mockResolvedValueOnce({ data: [transaction(1)], meta: { next_cursor: null } })
  const client = { listTransactions }
  const { result } = renderHook(() => useTransactions(client, {}))

  await waitFor(() => expect(result.current.hasMore).toBe(false))
  await act(async () => {
    await result.current.loadMore()
  })

  expect(listTransactions).toHaveBeenCalledTimes(1)
})

it('reports a failure rather than throwing, and lets reload retry', async () => {
  const listTransactions = vi.fn().mockRejectedValueOnce(new Error('network')).mockResolvedValueOnce({
    data: [transaction(1)],
    meta: { next_cursor: null },
  })
  const client = { listTransactions }
  const { result } = renderHook(() => useTransactions(client, {}))

  await waitFor(() => expect(result.current.error).toBe(true))

  act(() => result.current.reload())
  await waitFor(() => expect(result.current.items).toHaveLength(1))
  expect(result.current.error).toBe(false)
})

// The race the brief names by name: changing a filter fires a second request while the
// first is still in flight, and the first happens to settle *after* the second. Without
// the effect's `ignore` cleanup, the stale (older) response would land last and clobber
// the fresher one.
it('does not let a stale filter response overwrite a newer one', async () => {
  const resolvers: Array<(page: CursorPage<ApiTransaction>) => void> = []
  const listTransactions = vi.fn().mockImplementation(
    () => new Promise<CursorPage<ApiTransaction>>((resolve) => resolvers.push(resolve)),
  )
  const client = { listTransactions }

  const { result, rerender } = renderHook(({ filters }) => useTransactions(client, filters), {
    initialProps: { filters: { category_id: 1 } },
  })
  rerender({ filters: { category_id: 2 } })

  await waitFor(() => expect(resolvers).toHaveLength(2))

  // The second (fresher) request settles first ...
  resolvers[1]({ data: [transaction(2)], meta: { next_cursor: null } })
  await waitFor(() => expect(result.current.items.map((item) => item.id)).toEqual([2]))

  // ... and only afterwards does the first (now-stale) request settle.
  await act(async () => {
    resolvers[0]({ data: [transaction(1)], meta: { next_cursor: null } })
    await Promise.resolve()
    await Promise.resolve()
  })

  expect(result.current.items.map((item) => item.id)).toEqual([2])
})
