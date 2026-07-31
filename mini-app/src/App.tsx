import { useState } from 'react'
import type { SessionState } from './auth/useSession'
import { strings } from './strings'
import { useTheme } from './telegram/useTheme'
import { EmptyState } from './ui/EmptyState'
import { ErrorState } from './ui/ErrorState'
import { Tabs, type TabId, type TabItem } from './ui/Tabs'

export type AppProps = {
  session: SessionState
  /** Wired to `useSession`'s `retry` in `AppRoot`; omitted (and so hidden) in tests that don't need it. */
  onRetry?: () => void
}

const TAB_ITEMS: TabItem[] = [
  { id: 'add', label: strings.tabs.add },
  { id: 'reports', label: strings.tabs.reports },
  { id: 'history', label: strings.tabs.history },
]

// The app shell: renders exactly one of the session states. Only `active` gets the tab
// bar — every other state is a full-screen message with nothing else on it. Takes
// `session` as a prop (rather than calling `useSession` itself) so it is testable
// without mocking the hook or the network; `AppRoot` wires the real session.
export function App({ session, onRetry }: AppProps) {
  useTheme()
  const [tab, setTab] = useState<TabId>('add')

  if (session.kind === 'loading') {
    return <EmptyState message={strings.session.loading} />
  }

  if (session.kind === 'pending') {
    return <EmptyState message={strings.session.pending} />
  }

  if (session.kind === 'refused') {
    return <ErrorState message={session.message} />
  }

  if (session.kind === 'error') {
    return (
      <ErrorState
        message={session.message}
        actionLabel={onRetry ? strings.common.retry : undefined}
        onAction={onRetry}
      />
    )
  }

  return (
    <div className="flex min-h-screen flex-col" style={{ background: 'var(--tg-bg)', color: 'var(--tg-text)' }}>
      <main
        className="flex-1"
        role="tabpanel"
        id={`panel-${tab}`}
        aria-labelledby={`tab-${tab}`}
      />
      <Tabs value={tab} onChange={setTab} items={TAB_ITEMS} />
    </div>
  )
}
