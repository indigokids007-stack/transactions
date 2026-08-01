import { useEffect } from 'react'

const DEFAULT_DURATION_MS = 4000

type ToastProps = {
  message: string
  action?: string
  onAction?: () => void
  /** Called once the toast should disappear on its own. Omit to keep it up indefinitely. */
  onDismiss?: () => void
  /** Milliseconds before `onDismiss` fires. Defaults to 4s; only meaningful with `onDismiss`. */
  duration?: number
}

// A transient banner (the entry screen uses it for the post-save "Undo" offer), fixed
// above the app's bottom tab bar so the two never overlap. Synchronising with a timer is
// an external system, so this is a genuine Effect: it schedules `onDismiss` once on
// mount and clears the timeout on cleanup, rather than leaking a timer past unmount or
// past a re-render with a new `message`.
export function Toast({ message, action, onAction, onDismiss, duration = DEFAULT_DURATION_MS }: ToastProps) {
  useEffect(() => {
    if (!onDismiss) return
    const timer = setTimeout(onDismiss, duration)
    return () => clearTimeout(timer)
  }, [onDismiss, duration])

  return (
    <div
      role="status"
      className="absolute z-10 flex items-center justify-between"
      style={{
        left: 22,
        right: 22,
        bottom: 110,
        gap: 12,
        background: 'var(--teal-900)',
        borderRadius: 20,
        padding: '15px 18px',
        boxShadow: 'var(--shadow-toast)',
        animation: 'toastIn .22s ease-out',
      }}
    >
      <span style={{ font: '600 13px/1 "Plus Jakarta Sans"', color: '#fff' }}>{message}</span>
      {action && onAction ? (
        <button
          type="button"
          onClick={onAction}
          className="rounded-full"
          style={{ border: 0, background: 'rgba(255,255,255,.16)', color: '#9fe8d3', padding: '8px 14px', font: '700 12px/1 "Plus Jakarta Sans"' }}
        >
          {action}
        </button>
      ) : null}
    </div>
  )
}
