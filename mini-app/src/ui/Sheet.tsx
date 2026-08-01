import type { ReactNode } from 'react'

type SheetProps = {
  label: string
  open: boolean
  onToggle: () => void
  children: ReactNode
}

// A generic collapsible section: a labelled toggle button plus a body that only mounts
// while open. Kept dumb and controlled (the caller owns `open`) so `DetailsSheet` can
// derive the open state during render instead of syncing it through an effect.
export function Sheet({ label, open, onToggle, children }: SheetProps) {
  return (
    <div className="border-t" style={{ borderColor: 'var(--tg-hint)' }}>
      <button
        type="button"
        onClick={onToggle}
        aria-expanded={open}
        className="flex w-full items-center justify-between px-4 py-3 text-sm"
      >
        <span>{label}</span>
        <span aria-hidden="true">{open ? '▲' : '▼'}</span>
      </button>
      {open ? <div className="space-y-3 px-4 pb-4">{children}</div> : null}
    </div>
  )
}
