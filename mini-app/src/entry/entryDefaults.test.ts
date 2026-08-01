/// <reference types="node" />
// The reference above is this file's own, narrow opt-in to `process` typings — pinning
// `TZ` is the only way to make "local time differs from UTC" deterministic across
// machines, and this is the one place that needs it; the app itself never reads `process`.
import { valuesFromDefaults } from './entryDefaults'
import { bootstrapFixture } from '../test/fixtures'

const originalTZ = process.env.TZ

afterEach(() => {
  vi.useRealTimers()
  process.env.TZ = originalTZ
})

// The review's exact scenario: Uzbekistan is UTC+5, so 2026-08-01T00:30 local is still
// 2026-07-31T19:30 UTC. `toISOString().slice(0, 10)` would read the UTC calendar and
// misdate the entry a day early; a person recording a late-evening expense would have it
// land in yesterday instead of today.
it('dates a new entry by the local calendar day, not the UTC one', () => {
  process.env.TZ = 'Asia/Tashkent'
  vi.useFakeTimers()
  vi.setSystemTime(new Date('2026-08-01T00:30:00+05:00'))

  const values = valuesFromDefaults(bootstrapFixture)

  expect(values.occurredOn).toBe('2026-08-01')
})
