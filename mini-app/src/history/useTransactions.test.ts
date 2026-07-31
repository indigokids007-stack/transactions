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

// The MAJOR finding by name: category A's next page is still in flight when the user
// switches to category B. B's own first page must win, and A's page — arriving late —
// must never land on top of it via `setItems(current => [...current, ...page.data])`.
it('discards a stale loadMore page that settles after the filters changed', async () => {
  const resolvers: Array<(page: CursorPage<ApiTransaction>) => void> = []
  const listTransactions = vi.fn().mockImplementation(
    () => new Promise<CursorPage<ApiTransaction>>((resolve) => resolvers.push(resolve)),
  )
  const client = { listTransactions }

  const { result, rerender } = renderHook(({ filters }) => useTransactions(client, filters), {
    initialProps: { filters: { category_id: 1 } },
  })

  await waitFor(() => expect(resolvers).toHaveLength(1))
  act(() => resolvers[0]({ data: [transaction(1)], meta: { next_cursor: 'a-next' } }))
  await waitFor(() => expect(result.current.hasMore).toBe(true))

  // Category A's next page starts loading ...
  let loadMorePromise: Promise<void> = Promise.resolve()
  act(() => {
    loadMorePromise = result.current.loadMore()
  })
  await waitFor(() => expect(resolvers).toHaveLength(2))

  // ... but before it resolves, the user switches to category B, whose own first page
  // resolves first.
  rerender({ filters: { category_id: 2 } })
  await waitFor(() => expect(resolvers).toHaveLength(3))
  act(() => resolvers[2]({ data: [transaction(2)], meta: { next_cursor: null } }))
  await waitFor(() => expect(result.current.items.map((item) => item.id)).toEqual([2]))

  // Category A's stale next page finally settles — it must not append to B's list.
  await act(async () => {
    resolvers[1]({ data: [transaction(3)], meta: { next_cursor: null } })
    await loadMorePromise
  })

  expect(result.current.items.map((item) => item.id)).toEqual([2])
})

// The second half of the same finding: no loading guard meant the observer could ask
// for the same cursor twice concurrently.
it('does not start a second request while a loadMore call is already in flight', async () => {
  let resolveSecondPage: ((page: CursorPage<ApiTransaction>) => void) | null = null
  const listTransactions = vi
    .fn()
    .mockResolvedValueOnce({ data: [transaction(1)], meta: { next_cursor: 'a-next' } })
    .mockImplementationOnce(
      () =>
        new Promise<CursorPage<ApiTransaction>>((resolve) => {
          resolveSecondPage = resolve
        }),
    )
  const client = { listTransactions }
  const { result } = renderHook(() => useTransactions(client, {}))

  await waitFor(() => expect(result.current.hasMore).toBe(true))

  let first: Promise<void> = Promise.resolve()
  let second: Promise<void> = Promise.resolve()
  act(() => {
    first = result.current.loadMore()
    second = result.current.loadMore()
  })

  await act(async () => {
    resolveSecondPage?.({ data: [transaction(2)], meta: { next_cursor: null } })
    await Promise.all([first, second])
  })

  expect(listTransactions).toHaveBeenCalledTimes(2)
})
