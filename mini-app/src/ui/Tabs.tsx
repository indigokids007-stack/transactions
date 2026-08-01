import { Check, History, PieChart, Plus } from 'lucide-react'

export type TabId = 'add' | 'reports' | 'history'

export type TabItem = {
  id: TabId
  label: string
}

// Takes over the center "Add" button when a valid entry is ready to save — `App.tsx`
// wires this to `EntryScreen`'s form state. Not shown, and the button falls back to its
// normal "switch to Add" behaviour, whenever `enabled` is false (nothing to save yet, or
// another tab is showing).
export type TabsAddAction = {
  enabled: boolean
  label: string
  onClick: () => void
}

type TabsProps = {
  value: TabId
  onChange: (id: TabId) => void
  items: TabItem[]
  addAction?: TabsAddAction
}

const ICONS: Record<TabId, typeof PieChart> = {
  add: Plus,
  reports: PieChart,
  history: History,
}

// A controlled tab bar: the parent owns `value` and receives the requested change
// through `onChange`. `role="tablist"`/`role="tab"` plus `aria-selected`, `aria-controls`
// and `tabIndex` are unchanged by this component's visual redesign — a floating pill with
// a masked notch and a raised center "Add" FAB. Render order (reports, history, then the
// FAB absolutely positioned) differs from `items`' DOM order deliberately — `App.tsx`'s
// `TAB_ITEMS` stays `add, reports, history` and is never reordered.
export function Tabs({ value, onChange, items, addAction }: TabsProps) {
  const byId = Object.fromEntries(items.map((item) => [item.id, item])) as Record<TabId, TabItem | undefined>
  const reports = byId.reports
  const history = byId.history
  const add = byId.add
  const saving = addAction?.enabled ?? false

  function tab(item: TabItem) {
    const selected = item.id === value
    const Icon = ICONS[item.id]
    return (
      <button
        key={item.id}
        type="button"
        role="tab"
        id={`tab-${item.id}`}
        aria-selected={selected}
        aria-controls={`panel-${item.id}`}
        tabIndex={selected ? 0 : -1}
        onClick={() => onChange(item.id)}
        className="flex flex-col items-center gap-[5px] py-2"
        style={{ width: 104, color: selected ? 'var(--teal-600)' : 'var(--muted-3)', fontWeight: selected ? 700 : 500 }}
      >
        <Icon size={22} aria-hidden="true" />
        <span className="text-[11px]">{item.label}</span>
      </button>
    )
  }

  return (
    <div style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}>
      <div role="tablist" className="relative" style={{ height: 76 }}>
        <div
          aria-hidden="true"
          className="tabbar-notch absolute inset-0"
          style={{ background: 'var(--surface)', borderRadius: 30, boxShadow: 'var(--shadow-tabbar)' }}
        />
        <div className="relative flex h-full items-center justify-between px-3.5">
          {reports ? tab(reports) : null}
          {history ? tab(history) : null}
        </div>
        {add ? (
          <button
            key={add.id}
            type="button"
            role="tab"
            id={`tab-${add.id}`}
            aria-selected={add.id === value}
            aria-controls={`panel-${add.id}`}
            aria-label={saving ? addAction!.label : add.label}
            tabIndex={add.id === value ? 0 : -1}
            onClick={saving ? addAction!.onClick : () => onChange(add.id)}
            className="absolute flex items-center justify-center rounded-full"
            style={{
              left: '50%',
              top: 0,
              transform: 'translate(-50%,-52%)',
              width: 62,
              height: 62,
              border: 0,
              color: '#fff',
              background: 'var(--grad-fab)',
              boxShadow: 'var(--shadow-fab)',
              outline: add.id === value ? '3px solid rgba(251,227,203,.9)' : undefined,
              outlineOffset: add.id === value ? 2 : undefined,
            }}
          >
            {saving ? <Check size={26} aria-hidden="true" /> : <Plus size={26} aria-hidden="true" />}
          </button>
        ) : null}
      </div>
    </div>
  )
}
