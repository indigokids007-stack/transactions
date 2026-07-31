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
      <main className="flex-1">
        {TAB_ITEMS.map((item) => (
          <div
            key={item.id}
            role="tabpanel"
            id={`panel-${item.id}`}
            aria-labelledby={`tab-${item.id}`}
            // All three panels stay mounted; only the active one is shown. Hiding
            // rather than unmounting keeps each tab's own state alive across a switch
            // (the design doc's requirement) and gives `Tabs`' `aria-controls` a real
            // target for every tab, not just the active one.
            hidden={item.id !== tab}
          >
            <PanelPlaceholder label={item.label} />
          </div>
        ))}
      </main>
      <Tabs value={tab} onChange={setTab} items={TAB_ITEMS} />
    </div>
  )
}

// Stands in for the real Add/Reports/History screens the later tasks build. Holds its
// own state (an ordinary controlled input) purely to prove, and let tests prove, that
// switching tabs hides a panel rather than tearing it down.
function PanelPlaceholder({ label }: { label: string }) {
  const [note, setNote] = useState('')
  return (
    <label className="block p-4 text-sm">
      {label}
      <input
        type="text"
        value={note}
        onChange={(event) => setNote(event.target.value)}
        className="mt-1 block w-full rounded border px-2 py-1"
      />
    </label>
  )
}
