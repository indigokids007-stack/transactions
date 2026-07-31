type ToastProps = {
  message: string
  action?: string
  onAction?: () => void
}

// A transient, dismiss-free banner (the entry screen uses it for the post-save "Undo"
// offer). The action only renders when the caller supplies both a label and a handler.
export function Toast({ message, action, onAction }: ToastProps) {
  return (
    <div
      role="status"
      className="fixed inset-x-4 bottom-4 flex items-center justify-between gap-4 rounded-xl px-4 py-3 shadow-lg"
      style={{ background: 'var(--tg-button)', color: 'var(--tg-button-text)' }}
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
