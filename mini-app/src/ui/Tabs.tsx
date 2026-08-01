import { History, PieChart, Plus } from 'lucide-react'

export type TabId = 'add' | 'reports' | 'history'

export type TabItem = {
  id: TabId
  label: string
}

type TabsProps = {
  value: TabId
  onChange: (id: TabId) => void
  items: TabItem[]
}

const ICONS: Record<TabId, typeof PieChart> = {
  add: Plus,
  reports: PieChart,
  history: History,
}

// A controlled tab bar: the parent owns `value` and receives the requested change
// through `onChange`. `role="tablist"`/`role="tab"` plus `aria-selected` and
// `aria-controls` let callers (and tests) find these by role instead of by class name —
// unchanged by this component's visual redesign (icon-based, center "Add" FAB).
export function Tabs({ value, onChange, items }: TabsProps) {
  return (
    <div
      role="tablist"
      className="flex items-end justify-around border-t px-2 pt-2"
      style={{
        borderColor: 'var(--border)',
        background: 'var(--surface-card)',
        paddingBottom: 'max(0.5rem, env(safe-area-inset-bottom))',
      }}
    >
      {items.map((item) => {
        const selected = item.id === value
        const Icon = ICONS[item.id]

        if (item.id === 'add') {
          return (
            <button
              key={item.id}
              type="button"
              role="tab"
              id={`tab-${item.id}`}
              aria-selected={selected}
              aria-controls={`panel-${item.id}`}
              aria-label={item.label}
              tabIndex={selected ? 0 : -1}
              className="-mt-6 flex h-12 w-12 items-center justify-center rounded-full shadow-lg"
              style={{ background: 'var(--accent)', color: 'var(--accent-text)' }}
              onClick={() => onChange(item.id)}
            >
              <Icon size={24} aria-hidden="true" />
            </button>
          )
        }

        return (
          <button
            key={item.id}
            type="button"
            role="tab"
            id={`tab-${item.id}`}
            aria-selected={selected}
            aria-controls={`panel-${item.id}`}
            tabIndex={selected ? 0 : -1}
            className="flex flex-1 flex-col items-center gap-1 py-2 text-xs"
            style={{ color: selected ? 'var(--accent)' : 'var(--tg-hint)', fontWeight: selected ? 600 : 400 }}
            onClick={() => onChange(item.id)}
          >
            <Icon size={20} aria-hidden="true" />
            {item.label}
          </button>
        )
      })}
    </div>
  )
}
