type EmptyStateProps = {
  message: string
}

// A neutral, full-space message: "there is nothing to show", not "something failed".
// Used for the loading and pending-approval screens now; later tasks reuse it for an
// empty report period or an empty history list.
export function EmptyState({ message }: EmptyStateProps) {
  return (
    <div
      className="flex min-h-screen flex-col items-center justify-center p-6 text-center"
      style={{ background: 'var(--tg-bg)', color: 'var(--tg-text)' }}
    >
      <p>{message}</p>
    </div>
  )
}
