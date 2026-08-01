type ErrorStateProps = {
  message: string
  actionLabel?: string
  onAction?: () => void
}

// A full-space failure message with an optional retry action. The action only renders
// when the caller supplies both a label and a handler — a refused (403) session has
// nothing to retry, so it passes neither.
export function ErrorState({ message, actionLabel, onAction }: ErrorStateProps) {
  return (
    <div
      role="alert"
      className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center"
      style={{ background: 'var(--bg)', color: 'var(--ink)' }}
    >
      <p>{message}</p>
      {actionLabel && onAction ? (
        <button
          type="button"
          onClick={onAction}
          className="rounded-full"
          style={{ border: 0, padding: '10px 16px', background: 'var(--grad-action)', color: '#fff', boxShadow: 'var(--shadow-action)' }}
        >
          {actionLabel}
        </button>
      ) : null}
    </div>
  )
}
