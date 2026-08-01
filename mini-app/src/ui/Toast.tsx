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
      className="fixed inset-x-4 bottom-20 flex items-center justify-between gap-4 rounded-xl px-4 py-3 shadow-lg"
      style={{ background: 'var(--accent)', color: 'var(--tg-button-text)' }}
    >
      <span>{message}</span>
      {action && onAction ? (
        <button type="button" onClick={onAction} className="font-semibold underline">
          {action}
        </button>
      ) : null}
    </div>
  )
}
