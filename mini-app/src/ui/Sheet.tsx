import type { ReactNode } from 'react'
import { strings } from '../strings'

type SheetProps = {
  label: string
  open: boolean
  onClose: () => void
  children: ReactNode
}

// A bottom modal: backdrop plus a panel pinned to the bottom, closed by tapping the
// backdrop or the ✕. Unmounts entirely while closed. The caller owns `open` and the
// trigger that flips it — this component only ever renders the modal itself, never the
// button that opens it (see `EntryScreen`'s `aria-expanded` trigger).
export function Sheet({ label, open, onClose, children }: SheetProps) {
  if (!open) return null

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="absolute inset-0 z-10 flex flex-col justify-end"
      style={{ background: 'rgba(11,45,38,.42)' }}
      onClick={onClose}
    >
      <div
        className="flex flex-col gap-3"
        style={{
          background: 'var(--surface)',
          borderRadius: 'var(--r-sheet) var(--r-sheet) 0 0',
          padding: '12px 22px 26px',
          animation: 'sheetIn .26s cubic-bezier(.32,.72,.3,1)',
        }}
        onClick={(event) => event.stopPropagation()}
      >
        <div
          aria-hidden="true"
          style={{ width: 44, height: 5, borderRadius: 999, background: 'var(--line-2)', margin: '0 auto' }}
        />
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-extrabold" style={{ color: 'var(--ink)' }}>
            {label}
          </h3>
          <button
            type="button"
            onClick={onClose}
            aria-label={strings.common.close}
            className="flex h-8 w-8 items-center justify-center rounded-full text-base font-semibold"
            style={{ background: 'var(--pill-bg)', color: 'var(--ink-2)', border: 0 }}
          >
            ✕
          </button>
        </div>
        <div className="flex flex-col gap-3">{children}</div>
      </div>
    </div>
  )
}
