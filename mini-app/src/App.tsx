import { useState } from 'react'
import type { SessionState } from './auth/useSession'
import type { ApiClient } from './api/client'
import { EntryScreen } from './entry/EntryScreen'
import { ReportsScreen } from './reports/ReportsScreen'
import { usePeriod } from './reports/usePeriod'
import { strings } from './strings'
import { useTheme } from './telegram/useTheme'
import { EmptyState } from './ui/EmptyState'
import { ErrorState } from './ui/ErrorState'
import { Tabs, type TabId, type TabItem } from './ui/Tabs'

export type AppProps = {
  session: SessionState
  /** Wired to `useSession`'s `retry` in `AppRoot`; omitted (and so hidden) in tests that don't need it. */
  onRetry?: () => void
  /** Wired to the real `ApiClient` in `AppRoot`; the Add panel needs it once the session is active. */
  client?: ApiClient
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
export function App({ session, onRetry, client }: AppProps) {
  useTheme()
  const [tab, setTab] = useState<TabId>('add')
  // Called unconditionally, like every hook, even on the states below that return before
  // reaching the tab bar — the Reports panel this feeds stays mounted across a tab
  // switch (see the `hidden` panels below), so its period must survive right along with
  // it rather than reset every time the user looks away and back.
  const period = usePeriod()

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
            {item.id === 'add' && client ? (
              <EntryScreen bootstrap={session.bootstrap} client={client} active={tab === 'add'} />
            ) : item.id === 'reports' && client ? (
              <ReportsScreen
                user={session.user}
                client={client}
                period={period}
                exponents={session.bootstrap.currencies}
                dimensions={session.bootstrap.dimensions}
              />
            ) : (
              <PanelPlaceholder label={item.label} />
            )}
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
