import { useEffect, useState } from 'react'
import type { ApiClient } from '../api/client'
import type { ApiTransaction, TransactionListParams } from '../api/types'

/** What a caller may filter the ledger by; `cursor` is the hook's own concern. */
export type HistoryFilters = Omit<TransactionListParams, 'cursor'>

export type UseTransactions = {
  items: ApiTransaction[]
  loadMore: () => Promise<void>
  hasMore: boolean
  loading: boolean
  error: boolean
  reload: () => void
  /** Drops one row locally once the server has confirmed the delete. */
  remove: (id: number) => void
  /** Swaps one row for the server's own copy once an edit is confirmed saved. */
  replace: (transaction: ApiTransaction) => void
}

type ListTransactions = Pick<ApiClient, 'listTransactions'>

// Builds the request params from `filters` field by field (never a `...filters` spread)
// so every value the effect below reads is named in its dependency array, exactly the
// way `SummaryView`'s fetch effect reads `period.from`/`period.to` rather than `period`
// itself.
function buildParams(filters: HistoryFilters, cursor?: string): TransactionListParams {
  return {
    from: filters.from,
    to: filters.to,
    type: filters.type,
    category_id: filters.category_id,
    currency: filters.currency,
    user_id: filters.user_id,
    department_id: filters.department_id,
    dimension: filters.dimension,
    cursor,
  }
}

// Fetches the first page whenever a filter changes (or `reload` is called), and appends
// further pages only when a caller explicitly asks via `loadMore` — the bottom-of-list
// intersection observer in `TransactionList`. Mirrors `SummaryView`'s fetch effect: the
// `ignore` flag is what keeps a stale filter change from clobbering a fresher one that
// happens to resolve first. `filters.dimension` is an object a caller may recreate every
// render even when its contents haven't changed, so the effect depends on its serialised
// form rather than its identity — the effect body still reads the live `filters` value,
// which is correct because the closure that runs is always the one from the render that
// last actually changed `dimensionKey`.
export function useTransactions(client: ListTransactions, filters: HistoryFilters): UseTransactions {
  const [items, setItems] = useState<ApiTransaction[]>([])
  const [cursor, setCursor] = useState<string | null>(null)
  const [hasMore, setHasMore] = useState(false)
  // Starts `true`, not `false`: the effect below always fires a fetch on mount, so there
  // is no real "idle, nothing requested yet" state for the first render to describe —
  // starting `false` would let a caller flash an empty-list message for one paint before
  // the effect has had a chance to run.
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)
  const [reloadToken, setReloadToken] = useState(0)

  const dimensionKey = JSON.stringify(filters.dimension ?? {})

  useEffect(() => {
    let ignore = false
    setLoading(true)
    setError(false)

    client
      .listTransactions(buildParams(filters))
      .then((page) => {
        if (ignore) return
        setItems(page.data)
        setCursor(page.meta.next_cursor)
        setHasMore(page.meta.next_cursor !== null)
      })
      .catch(() => {
        if (!ignore) setError(true)
      })
      .finally(() => {
        if (!ignore) setLoading(false)
      })

    return () => {
      ignore = true
    }
  }, [
    client,
    filters.from,
    filters.to,
    filters.type,
    filters.category_id,
    filters.currency,
    filters.user_id,
    filters.department_id,
    dimensionKey,
    reloadToken,
  ])

  async function loadMore(): Promise<void> {
    if (!hasMore || cursor === null) return

    setLoading(true)
    try {
      const page = await client.listTransactions(buildParams(filters, cursor))
      setItems((current) => [...current, ...page.data])
      setCursor(page.meta.next_cursor)
      setHasMore(page.meta.next_cursor !== null)
    } catch {
      setError(true)
    } finally {
      setLoading(false)
    }
  }

  function reload(): void {
    setReloadToken((token) => token + 1)
  }

  function remove(id: number): void {
    setItems((current) => current.filter((item) => item.id !== id))
  }

  function replace(transaction: ApiTransaction): void {
    setItems((current) => current.map((item) => (item.id === transaction.id ? transaction : item)))
  }

  return { items, loadMore, hasMore, loading, error, reload, remove, replace }
}
