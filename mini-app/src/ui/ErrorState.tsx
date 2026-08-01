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
      style={{ background: 'var(--tg-bg)', color: 'var(--tg-text)' }}
    >
      <p>{message}</p>
      {actionLabel && onAction ? (
        <button
          type="button"
          onClick={onAction}
          className="rounded-full px-4 py-2"
          style={{ background: 'var(--accent)', color: 'var(--accent-text)' }}
        >
          {actionLabel}
        </button>
      ) : null}
    </div>
  )
}
