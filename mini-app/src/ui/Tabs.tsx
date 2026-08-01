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

// A controlled tab bar: the parent owns `value` and receives the requested change
// through `onChange`. `role="tablist"`/`role="tab"` plus `aria-selected` and
// `aria-controls` let callers (and tests) find these by role instead of by class name.
export function Tabs({ value, onChange, items }: TabsProps) {
  return (
    <div role="tablist" className="flex border-t" style={{ borderColor: 'var(--tg-hint)' }}>
      {items.map((item) => {
        const selected = item.id === value
        return (
          <button
            key={item.id}
            type="button"
            role="tab"
            id={`tab-${item.id}`}
            aria-selected={selected}
            aria-controls={`panel-${item.id}`}
            tabIndex={selected ? 0 : -1}
            className="flex-1 py-3 text-sm"
            style={{
              color: selected ? 'var(--tg-button)' : 'var(--tg-hint)',
              fontWeight: selected ? 600 : 400,
            }}
            onClick={() => onChange(item.id)}
          >
            {item.label}
          </button>
        )
      })}
    </div>
  )
}
