import { renderHook, waitFor } from '@testing-library/react'
import { useSession } from './useSession'
import type { AuthExchange } from '../api/types'

// `user` and `categories`/`dimensions` are NOT wrapped in `data` here: verified against
// a live `/api/bootstrap` response (see the task report). Only a resource returned as a
// route's direct top-level value gets Laravel's automatic `data` wrapper; nested inside
// this plain array, none of the three do.
const bootstrapBody = {
  user: { id: 1, status: 'active', permissions: { can_see_all: false, can_manage: false } },
  categories: [],
  dimensions: [],
  currencies: { UZS: 0 },
  defaults: { type: 'expense', currency: 'UZS', category_id: null, dimension_values: {} },
}

it('becomes active when the exchange returns a token', async () => {
  const client = {
    authenticate: vi.fn().mockResolvedValue({ token: 'tok', user: { id: 1, status: 'active' } }),
    setToken: vi.fn(),
    bootstrap: vi.fn().mockResolvedValue(bootstrapBody),
  }

  const { result } = renderHook(() => useSession(client, 'init-data'))

  await waitFor(() => expect(result.current.state.kind).toBe('active'))
  expect(client.setToken).toHaveBeenCalledWith('tok')
})

it('stays pending and never loads the bootstrap when the token is null', async () => {
  const client = {
    authenticate: vi.fn().mockResolvedValue({ token: null, user: { id: 1, status: 'pending' } }),
    setToken: vi.fn(),
    bootstrap: vi.fn(),
  }

  const { result } = renderHook(() => useSession(client, 'init-data'))

  await waitFor(() => expect(result.current.state.kind).toBe('pending'))
  expect(client.bootstrap).not.toHaveBeenCalled()
})

it('reports a refusal when the exchange is rejected with a 403', async () => {
  const client = {
    authenticate: vi.fn().mockRejectedValue({ status: 403, message: 'Registration is closed.' }),
    setToken: vi.fn(),
    bootstrap: vi.fn(),
  }

  const { result } = renderHook(() => useSession(client, 'init-data'))

  await waitFor(() => expect(result.current.state.kind).toBe('refused'))
  expect(result.current.state).toMatchObject({ message: 'Registration is closed.' })
})

it('reports a generic error for anything other than a 403', async () => {
  const client = {
    authenticate: vi.fn().mockRejectedValue({ status: 500, message: 'Server exploded.' }),
    setToken: vi.fn(),
    bootstrap: vi.fn(),
  }

  const { result } = renderHook(() => useSession(client, 'init-data'))

  await waitFor(() => expect(result.current.state.kind).toBe('error'))
  expect(result.current.state).toMatchObject({ message: 'Server exploded.' })
})

it('discards a stale exchange if initData changes before it resolves', async () => {
  // A slow first exchange (for 'stale-init-data') resolves only after a second one (for
  // 'fresh-init-data') has already settled the state. Without the ignore-flag cleanup,
  // the stale resolution would clobber the fresh, later state when it finally arrives.
  let resolveStale: ((exchange: AuthExchange) => void) | undefined

  const client = {
    authenticate: vi.fn((initData: string): Promise<AuthExchange> => {
      if (initData === 'stale-init-data') {
        return new Promise((resolve) => {
          resolveStale = resolve
        })
      }
      return Promise.resolve({ token: null, user: { id: 2, status: 'pending' } } as AuthExchange)
    }),
    setToken: vi.fn(),
    bootstrap: vi.fn(),
  }

  const { result, rerender } = renderHook(({ initData }) => useSession(client, initData), {
    initialProps: { initData: 'stale-init-data' },
  })

  rerender({ initData: 'fresh-init-data' })

  await waitFor(() => expect(result.current.state.kind).toBe('pending'))

  resolveStale?.({ token: 'stale-token', user: { id: 1 } } as AuthExchange)
  await Promise.resolve()
  await Promise.resolve()

  expect(result.current.state.kind).toBe('pending')
  expect(client.setToken).not.toHaveBeenCalled()
})

it('retries the exchange when asked to', async () => {
  const client = {
    authenticate: vi
      .fn()
      .mockRejectedValueOnce({ status: 500, message: 'Server exploded.' })
      .mockResolvedValueOnce({ token: 'tok', user: { id: 1, status: 'active' } }),
    setToken: vi.fn(),
    bootstrap: vi.fn().mockResolvedValue(bootstrapBody),
  }

  const { result } = renderHook(() => useSession(client, 'init-data'))

  await waitFor(() => expect(result.current.state.kind).toBe('error'))

  result.current.retry()

  await waitFor(() => expect(result.current.state.kind).toBe('active'))
  expect(client.authenticate).toHaveBeenCalledTimes(2)
})
