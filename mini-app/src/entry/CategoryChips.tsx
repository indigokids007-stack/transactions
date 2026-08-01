import type { ApiCategory } from '../api/types'
import { strings } from '../strings'

type CategoryChipsProps = {
  categories: ApiCategory[]
  type: 'income' | 'expense'
  selectedId: number | null
  defaultId: number | null
  onSelect: (id: number) => void
}

// Depth-first flatten, keeping only categories (parent or child) whose `applies_to`
// accepts the current transaction type. A parent that doesn't apply still surfaces its
// applicable children — `applies_to` is a leaf-level fact, not inherited.
function flatten(categories: ApiCategory[], type: 'income' | 'expense'): ApiCategory[] {
  return categories.flatMap((category) => {
    const children = flatten(category.children, type)
    const applies = category.applies_to === 'both' || category.applies_to === type
    return applies ? [category, ...children] : children
  })
}

// Puts the sticky default (the server's last-used category) first, leaving every other
// chip in the order the backend sent it.
function stickyFirst(categories: ApiCategory[], defaultId: number | null): ApiCategory[] {
  const index = defaultId === null ? -1 : categories.findIndex((category) => category.id === defaultId)
  if (index <= 0) return categories

  return [categories[index], ...categories.slice(0, index), ...categories.slice(index + 1)]
}

export function CategoryChips({ categories, type, selectedId, defaultId, onSelect }: CategoryChipsProps) {
  const chips = stickyFirst(flatten(categories, type), defaultId)

  return (
    <div
      role="group"
      aria-label={strings.entry.category}
      className="chips flex gap-2 overflow-x-auto"
      style={{ padding: '18px 22px 4px' }}
    >
      {chips.map((category) => {
        const selected = category.id === selectedId
        return (
          <button
            key={category.id}
            type="button"
            aria-pressed={selected}
            onClick={() => onSelect(category.id)}
            className="whitespace-nowrap rounded-full"
            style={{
              border: 0,
              padding: '11px 16px',
              font: '600 13px/1 "Plus Jakarta Sans"',
              background: selected ? 'var(--grad-action)' : 'var(--surface)',
              color: selected ? '#fff' : 'var(--ink-2)',
              boxShadow: selected ? '0 6px 16px rgba(239,139,60,.3)' : 'var(--shadow-chip)',
            }}
          >
            {category.name}
          </button>
        )
      })}
    </div>
  )
}
