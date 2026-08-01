import { App } from './App'
import { createClient } from './api/client'
import { useSession } from './auth/useSession'
import { webApp } from './telegram/webApp'

function readBaseUrl(): string {
  const value: unknown = import.meta.env.VITE_API_BASE_URL
  return typeof value === 'string' ? value : ''
}

// Created once, at module scope, when this module first loads — not inside `AppRoot`'s
// body. `useSession`'s effect lists its `client` argument in its dependency array, so a
// client re-created on every render (e.g. `createClient(...)` called from inside
// `AppRoot`) would re-run the Telegram auth exchange on every re-render instead of once
// per mount. See `AppRoot.test.tsx` for the regression test.
const client = createClient(readBaseUrl())

// Wires the real session to `App`. Kept separate from `App` itself so `App` stays
// testable with a plain `session` prop and no network or hook mocking.
export function AppRoot() {
  const { state, retry } = useSession(client, webApp().initData)
  return <App session={state} onRetry={retry} client={client} />
}
