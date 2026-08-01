import { useEffect, useRef } from 'react'
import { ArrowDownLeft, ArrowUpRight } from 'lucide-react'
import type { ApiTransaction } from '../api/types'
import { strings } from '../strings'
import { Money } from '../ui/Money'

export type TransactionListProps = {
  items: ApiTransaction[]
  exponents: Record<string, number>
  hasMore: boolean
  onLoadMore: () => void
  onSelect: (id: number) => void
}

// The ledger rows, plus a sentinel element the intersection observer below watches to
// call `onLoadMore` — an ordinary `<ul>`, no virtualisation, per the brief. Each row's
// `data-testid="transaction-<id>"` is what the edit/delete tests use to open a specific
// row's sheet.
export function TransactionList({ items, exponents, hasMore, onLoadMore, onSelect }: TransactionListProps) {
  const sentinelRef = useRef<HTMLDivElement | null>(null)

  // The IntersectionObserver is a genuine external system (the browser's own viewport
  // tracking), so watching the sentinel is a real effect, not something derivable at
  // render time — and it is disconnected in the cleanup so a row list that shrinks back
  // below `hasMore`, or unmounts entirely, never leaves a dangling observer calling
  // `onLoadMore` on a component that's gone.
  useEffect(() => {
    if (!hasMore) return

    const node = sentinelRef.current
    if (!node) return

    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) onLoadMore()
    })
    observer.observe(node)

    return () => observer.disconnect()
  }, [hasMore, onLoadMore])

  return (
    <ul className="divide-y" style={{ borderColor: 'var(--tg-hint)' }}>
      {items.map((item) => (
        <li key={item.id}>
          <button
            type="button"
            data-testid={`transaction-${item.id}`}
            onClick={() => onSelect(item.id)}
            className="flex w-full items-center justify-between px-4 py-3 text-left"
          >
            <span className="flex items-center gap-3">
              {item.type === 'income' ? (
                <ArrowUpRight size={18} style={{ color: 'var(--accent)' }} aria-hidden="true" />
              ) : (
                <ArrowDownLeft size={18} style={{ color: 'var(--accent-2)' }} aria-hidden="true" />
              )}
              <span>
                <span className="block text-sm">{item.category.name}</span>
                <span className="block text-xs opacity-70">
                  {strings.entry[item.type]} · {item.occurred_on}
                </span>
              </span>
            </span>
            <Money minor={item.amount_minor} currency={item.currency} exponents={exponents} />
          </button>
        </li>
      ))}
      {hasMore ? <div ref={sentinelRef} aria-hidden="true" data-testid="history-sentinel" /> : null}
    </ul>
  )
}
