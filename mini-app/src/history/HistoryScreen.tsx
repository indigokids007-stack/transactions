import { useMemo, useState } from 'react'
import type { ApiClient } from '../api/client'
import type { ApiDimension, ApiUser, Bootstrap } from '../api/types'
import { usePeriod } from '../reports/usePeriod'
import { strings } from '../strings'
import { EmptyState } from '../ui/EmptyState'
import { ErrorState } from '../ui/ErrorState'
import { Filters } from './Filters'
import { TransactionList } from './TransactionList'
import { TransactionSheet } from './TransactionSheet'
import { useTransactions, type HistoryFilters } from './useTransactions'

export type HistoryScreenProps = {
  client: ApiClient
  bootstrap: Bootstrap
  /**
   * Accepted for parity with `ReportsScreen`/`EntryScreen` and because the History tab
   * is naturally "whose ledger this is"; unused today because nothing here makes a
   * permission decision from it — the API is the sole authority on what a given user
   * may edit or delete (see `TransactionSheet`'s doc comment).
   */
  user: ApiUser
}

function applyDimensionChange(
  current: Record<string, number>,
  dimension: ApiDimension,
  valueId: number | undefined,
): Record<string, number> {
  const next = { ...current }
  if (valueId === undefined) {
    delete next[dimension.key]
  } else {
    next[dimension.key] = valueId
  }
  return next
}

// The history list, its filters, and the edit/delete sheet for whichever row is
// selected. `TransactionSheet` is mounted with `key={selected.id}` so opening a
// different row never carries the previous row's in-progress edits into the new one.
export function HistoryScreen({ client, bootstrap }: HistoryScreenProps) {
  const period = usePeriod()
  const [categoryId, setCategoryId] = useState<number | undefined>(undefined)
  const [currency, setCurrency] = useState<string | undefined>(undefined)
  const [dimensionValues, setDimensionValues] = useState<Record<string, number>>({})
  const [selectedId, setSelectedId] = useState<number | null>(null)

  const filters = useMemo<HistoryFilters>(
    () => ({
      from: period.from,
      to: period.to,
      category_id: categoryId,
      currency,
      dimension: Object.keys(dimensionValues).length > 0 ? dimensionValues : undefined,
    }),
    [period.from, period.to, categoryId, currency, dimensionValues],
  )

  const { items, loadMore, hasMore, loading, error, reload, remove, replace } = useTransactions(client, filters)
  const selected = items.find((item) => item.id === selectedId) ?? null

  return (
    <div className="flex flex-col">
      <Filters
        period={period}
        bootstrap={bootstrap}
        categoryId={categoryId}
        onCategoryChange={setCategoryId}
        currency={currency}
        onCurrencyChange={setCurrency}
        dimensionValues={dimensionValues}
        onDimensionChange={(dimension, valueId) =>
          setDimensionValues((current) => applyDimensionChange(current, dimension, valueId))
        }
      />

      {error ? (
        <ErrorState message={strings.history.loadFailed} actionLabel={strings.common.retry} onAction={reload} />
      ) : !loading && items.length === 0 ? (
        <EmptyState message={strings.history.empty} />
      ) : (
        <TransactionList
          items={items}
          exponents={bootstrap.currencies}
          hasMore={hasMore}
          onLoadMore={() => void loadMore()}
          onSelect={setSelectedId}
        />
      )}

      {selected ? (
        <TransactionSheet
          key={selected.id}
          transaction={selected}
          bootstrap={bootstrap}
          client={client}
          onClose={() => setSelectedId(null)}
          onSaved={(updated) => {
            replace(updated)
            setSelectedId(null)
          }}
          onDeleted={(id) => {
            remove(id)
            setSelectedId(null)
          }}
        />
      ) : null}
    </div>
  )
}
