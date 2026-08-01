import { act, renderHook, waitFor } from '@testing-library/react'
import { useRevisionCount } from './useRevisionCount'
import type { ApiTransactionRevision } from '../api/types'

function revision(id: number): ApiTransactionRevision {
  return { id, action: 'created', actor: { id: 1, name: 'Malika Karimova' }, snapshot: {}, created_at: '2026-07-15T00:00:00Z' }
}

it('starts loading, then reports the count once the request resolves', async () => {
  const client = { revisions: vi.fn().mockResolvedValue([revision(1), revision(2)]) }
  const { result } = renderHook(() => useRevisionCount(client, 1))

  expect(result.current).toEqual({ status: 'loading' })
  await waitFor(() => expect(result.current).toEqual({ status: 'loaded', count: 2 }))
})

// The review's finding: loading and failure used to both collapse to `null`, so a failed
// request read as the exact same indistinguishable placeholder as still-loading, with no
// way for the user to do anything about it.
it('reports failed rather than the same placeholder as loading, and offers a retry', async () => {
  const client = { revisions: vi.fn().mockRejectedValueOnce(new Error('network')).mockResolvedValueOnce([revision(1)]) }
  const { result } = renderHook(() => useRevisionCount(client, 1))

  await waitFor(() => expect(result.current.status).toBe('failed'))
  expect(result.current).not.toEqual({ status: 'loading' })

  const failedState = result.current
  if (failedState.status !== 'failed') throw new Error('expected failed state')
  act(() => failedState.retry())

  await waitFor(() => expect(result.current).toEqual({ status: 'loaded', count: 1 }))
  expect(client.revisions).toHaveBeenCalledTimes(2)
})
