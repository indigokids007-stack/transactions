import { act, renderHook } from '@testing-library/react'
import { usePeriod } from './usePeriod'

it('defaults to the current month', () => {
  const { result } = renderHook(() => usePeriod(new Date('2026-07-15T00:00:00Z')))

  expect(result.current.from).toBe('2026-07-01')
  expect(result.current.to).toBe('2026-07-31')
  expect(result.current.preset).toBe('this-month')
})

it('moves to the previous month and keeps its last day', () => {
  const { result } = renderHook(() => usePeriod(new Date('2026-03-15T00:00:00Z')))

  act(() => result.current.setPreset('last-month'))

  expect(result.current.from).toBe('2026-02-01')
  expect(result.current.to).toBe('2026-02-28')
  expect(result.current.preset).toBe('last-month')
})

// February 2028 is a leap year: this is the case a naive "last day = 28" shortcut would
// get wrong, and the one a fixed test double for `clock` cannot accidentally paper over.
it('keeps the 29th as the last day of a leap February', () => {
  const { result } = renderHook(() => usePeriod(new Date('2028-03-10T00:00:00Z')))

  act(() => result.current.setPreset('last-month'))

  expect(result.current.from).toBe('2028-02-01')
  expect(result.current.to).toBe('2028-02-29')
})

it('switches a custom range to the custom preset and back on request', () => {
  const { result } = renderHook(() => usePeriod(new Date('2026-07-15T00:00:00Z')))

  act(() => result.current.setRange('2026-01-05', '2026-01-20'))

  expect(result.current.from).toBe('2026-01-05')
  expect(result.current.to).toBe('2026-01-20')
  expect(result.current.preset).toBe('custom')

  act(() => result.current.setPreset('this-month'))

  expect(result.current.from).toBe('2026-07-01')
  expect(result.current.to).toBe('2026-07-31')
  expect(result.current.preset).toBe('this-month')
})
