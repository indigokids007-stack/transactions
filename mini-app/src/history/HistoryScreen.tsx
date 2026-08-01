import { useMemo, useState } from 'react'
import { Filter } from 'lucide-react'
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
   * Passed straight through to `TransactionSheet`, which mirrors
   * `TransactionPolicy::update` (`canManageTransaction`) to decide whether edit/delete
   * are offered for the selected row. The API remains the final authority on what a
   * given user may actually change — this only decides what to offer.
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
export function HistoryScreen({ client, bootstrap, user }: HistoryScreenProps) {
  const period = usePeriod()
  const [categoryId, setCategoryId] = useState<number | undefined>(undefined)
  const [currency, setCurrency] = useState<string | undefined>(undefined)
  const [dimensionValues, setDimensionValues] = useState<Record<string, number>>({})
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [filtersOpen, setFiltersOpen] = useState(false)

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
    <div className="flex flex-col" style={{ paddingBottom: 130 }}>
      <div
        style={{
          background: 'var(--surface)',
          padding: 'calc(54px + max(env(safe-area-inset-top), var(--tg-content-safe-top))) 22px 18px',
          borderRadius: '0 0 28px 28px',
        }}
      >
        <div className="flex items-end justify-between">
          <h2 className="m-0" style={{ font: '800 26px/1.1 "Plus Jakarta Sans"', color: 'var(--ink)', letterSpacing: '-.02em' }}>
            {strings.tabs.history}
          </h2>
          <button
            type="button"
            aria-pressed={filtersOpen}
            onClick={() => setFiltersOpen((current) => !current)}
            className="flex items-center gap-[7px] rounded-full"
            style={{
              border: 0,
              padding: '10px 14px',
              font: '600 12px/1 "Plus Jakarta Sans"',
              background: filtersOpen ? 'var(--teal-900)' : 'var(--pill-bg)',
              color: filtersOpen ? '#fff' : 'var(--ink-2)',
            }}
          >
            <Filter size={15} aria-hidden="true" />
            {strings.history.filters}
          </button>
        </div>

        <div className="mt-3.5">
          <Filters
            open={filtersOpen}
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
        </div>
      </div>

      <div style={{ padding: '16px 22px 0' }}>
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
      </div>

      {selected ? (
        <TransactionSheet
          key={selected.id}
          transaction={selected}
          bootstrap={bootstrap}
          user={user}
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
