/// <reference types="node" />
// The reference above is this file's own, narrow opt-in to `process` typings — pinning
// `TZ` is the only way to make "local time differs from UTC" deterministic across
// machines, and this is the one place that needs it; the app itself never reads `process`.
import { act, renderHook } from '@testing-library/react'
import { usePeriod } from './usePeriod'

const originalTZ = process.env.TZ

afterEach(() => {
  process.env.TZ = originalTZ
})

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

// The review's exact scenario: Uzbekistan is UTC+5, so 2026-08-01T00:30 local is still
// 2026-07-31T19:30 UTC. Reading the clock's UTC month would put this moment in July —
// the same instant a person opening the report at that hour experiences as August — so a
// naive UTC implementation would show them the wrong month and their own late-evening
// entry could vanish from the period they are looking at.
it('uses the local calendar month, not UTC, for a moment past local midnight', () => {
  process.env.TZ = 'Asia/Tashkent'
  const { result } = renderHook(() => usePeriod(new Date('2026-08-01T00:30:00+05:00')))

  expect(result.current.from).toBe('2026-08-01')
  expect(result.current.to).toBe('2026-08-31')
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
