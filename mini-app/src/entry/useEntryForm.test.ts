import { renderHook, act } from '@testing-library/react'
import { useEntryForm } from './useEntryForm'
import { bootstrapFixture, clientStub } from '../test/fixtures'
import { strings } from '../strings'

it('refuses to save while a required dimension is unanswered', () => {
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, clientStub()))

  act(() => result.current.setAmount('120000'))

  expect(result.current.canSave).toBe(false)
  expect(result.current.missingRequired).toEqual(['Filial'])
})

it('allows saving once the required dimension is answered', () => {
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, clientStub()))

  act(() => result.current.setAmount('120000'))
  act(() => result.current.setDimension(3, 9))

  expect(result.current.canSave).toBe(true)
})

it('refuses to save an amount the grammar rejects', () => {
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, clientStub()))

  act(() => result.current.setAmount('100 000'))
  act(() => result.current.setDimension(3, 9))

  expect(result.current.canSave).toBe(false)
})

it('reuses the idempotency key when the same attempt is retried', async () => {
  const client = clientStub()
  client.createTransaction = vi
    .fn()
    .mockRejectedValueOnce(new Error('network'))
    .mockResolvedValue({ data: { id: 1 } })
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, client))

  act(() => result.current.setAmount('120000'))
  act(() => result.current.setDimension(3, 9))
  await act(async () => {
    await result.current.save().catch(() => {})
  })
  await act(async () => {
    await result.current.save()
  })

  const [firstKey, secondKey] = (client.createTransaction as ReturnType<typeof vi.fn>).mock.calls.map(
    (call: unknown[]) => call[1],
  )
  expect(firstKey).toBe(secondKey)
})

it('generates a fresh key for the next entry', async () => {
  const client = clientStub()
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, client))

  act(() => result.current.setAmount('1000'))
  act(() => result.current.setDimension(3, 9))
  await act(async () => {
    await result.current.save()
  })
  act(() => result.current.setAmount('2000'))
  act(() => result.current.setDimension(3, 9))
  await act(async () => {
    await result.current.save()
  })

  const [firstKey, secondKey] = (client.createTransaction as ReturnType<typeof vi.fn>).mock.calls.map(
    (call: unknown[]) => call[1],
  )
  expect(firstKey).not.toBe(secondKey)
})

it('resets to the bootstrap defaults and offers undo after a successful save', async () => {
  const client = clientStub()
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, client))

  act(() => result.current.setAmount('120000'))
  act(() => result.current.setDimension(3, 9))
  act(() => result.current.setNote('taksi'))
  await act(async () => {
    await result.current.save()
  })

  expect(result.current.values.amountInput).toBe('')
  expect(result.current.values.note).toBe('')
  expect(result.current.values.dimensionValues).toEqual(bootstrapFixture.defaults.dimension_values)
  expect(result.current.lastSaved).toEqual({ id: 1 })

  await act(async () => {
    await result.current.undo()
  })
  expect(client.deleteTransaction).toHaveBeenCalledWith(1)
})

it('maps a 422 onto its fields without touching anything else', async () => {
  const client = clientStub()
  client.createTransaction = vi.fn().mockRejectedValue({
    status: 422,
    errors: { note: ['Note is too long.'] },
  })
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, client))

  act(() => result.current.setAmount('120000'))
  act(() => result.current.setDimension(3, 9))
  await act(async () => {
    await result.current.save()
  })

  expect(result.current.fieldErrors).toEqual({ note: ['Note is too long.'] })
  expect(result.current.values.amountInput).toBe('120000')
  expect(client.bootstrap).not.toHaveBeenCalled()
})

it('reports a generic failure notice for a non-422 rejection, without throwing', async () => {
  const client = clientStub()
  client.createTransaction = vi.fn().mockRejectedValue(new Error('network exploded'))
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, client))

  act(() => result.current.setAmount('120000'))
  act(() => result.current.setDimension(3, 9))
  await act(async () => {
    await result.current.save()
  })

  expect(result.current.notice).toBe(strings.entry.saveFailed)
  expect(result.current.lastSaved).toBeNull()
})

it('refetches bootstrap and clears the pick when a 422 names a stale reference', async () => {
  const client = clientStub()
  client.createTransaction = vi.fn().mockRejectedValue({
    status: 422,
    errors: { category_id: ['The selected category_id is invalid.'] },
  })
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, client))

  act(() => result.current.setAmount('120000'))
  act(() => result.current.setDimension(3, 9))
  await act(async () => {
    await result.current.save()
  })

  expect(client.bootstrap).toHaveBeenCalled()
  expect(result.current.notice).toBeTruthy()
  // The notice is the whole message for a stale reference: the raw backend string must
  // not also linger in `fieldErrors`, or the UI shows the same failure twice, once in
  // plain Uzbek and once as an untranslated validation sentence underneath it.
  expect(result.current.fieldErrors).toEqual({})
})

it('keeps an unrelated field error alongside a stale-reference notice', async () => {
  const client = clientStub()
  client.createTransaction = vi.fn().mockRejectedValue({
    status: 422,
    errors: {
      category_id: ['The selected category_id is invalid.'],
      note: ['Note is too long.'],
    },
  })
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, client))

  act(() => result.current.setAmount('120000'))
  act(() => result.current.setDimension(3, 9))
  await act(async () => {
    await result.current.save()
  })

  // `category_id`'s message is covered by the notice and dropped; `note`'s is not
  // stale-reference related and still needs to reach the user.
  expect(result.current.fieldErrors).toEqual({ note: ['Note is too long.'] })
})

it('clears lastSaved on dismissSaved without touching the server', async () => {
  const client = clientStub()
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, client))

  act(() => result.current.setAmount('120000'))
  act(() => result.current.setDimension(3, 9))
  await act(async () => {
    await result.current.save()
  })
  expect(result.current.lastSaved).not.toBeNull()

  act(() => result.current.dismissSaved())

  expect(result.current.lastSaved).toBeNull()
  expect(client.deleteTransaction).not.toHaveBeenCalled()
})
