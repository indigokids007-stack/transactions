import { useCallback, useEffect, useState } from 'react'
import type { ApiClient } from '../api/client'
import type { ApiUser, Bootstrap } from '../api/types'
import { strings } from '../strings'

export type SessionState =
  | { kind: 'loading' }
  | { kind: 'active'; user: ApiUser; bootstrap: Bootstrap }
  | { kind: 'pending'; user: ApiUser }
  | { kind: 'refused'; message: string }
  | { kind: 'error'; message: string }

// Only the three calls the exchange needs, so a test double can implement this instead
// of the full `ApiClient`.
export type SessionClient = Pick<ApiClient, 'authenticate' | 'setToken' | 'bootstrap'>

function readStatus(error: unknown): number | undefined {
  if (typeof error === 'object' && error !== null && 'status' in error) {
    const status = (error as { status?: unknown }).status
    return typeof status === 'number' ? status : undefined
  }
  return undefined
}

function readMessage(error: unknown, fallback: string): string {
  if (typeof error === 'object' && error !== null && 'message' in error) {
    const message = (error as { message?: unknown }).message
    if (typeof message === 'string') return message
  }
  return fallback
}

function toRejectedState(error: unknown): SessionState {
  if (readStatus(error) === 403) {
    return { kind: 'refused', message: readMessage(error, strings.session.refusedFallback) }
  }
  return { kind: 'error', message: readMessage(error, strings.session.errorFallback) }
}

export function useSession(
  client: SessionClient,
  initData: string,
): { state: SessionState; retry: () => void } {
  const [state, setState] = useState<SessionState>({ kind: 'loading' })
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    let ignore = false
    setState({ kind: 'loading' })

    async function exchange(): Promise<void> {
      try {
        const result = await client.authenticate(initData)
        if (ignore) return

        if (result.token === null) {
          setState({ kind: 'pending', user: result.user })
          return
        }

        client.setToken(result.token)
        const bootstrap = await client.bootstrap()
        if (ignore) return

        setState({ kind: 'active', user: result.user, bootstrap })
      } catch (error) {
        if (ignore) return
        setState(toRejectedState(error))
      }
    }

    void exchange()

    return () => {
      ignore = true
    }
  }, [client, initData, attempt])

  const retry = useCallback(() => setAttempt((n) => n + 1), [])

  return { state, retry }
}
