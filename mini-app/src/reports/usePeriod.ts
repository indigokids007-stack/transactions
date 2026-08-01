import { useState } from 'react'

export type PeriodPreset = 'this-month' | 'last-month' | 'custom'

/** What a report fetch needs from the picker: nothing about how the range was chosen. */
export type PeriodRange = {
  from: string
  to: string
}

export type Period = PeriodRange & {
  preset: PeriodPreset
  setPreset: (preset: 'this-month' | 'last-month') => void
  setRange: (from: string, to: string) => void
}

const MONTH_OFFSET: Record<'this-month' | 'last-month', number> = {
  'this-month': 0,
  'last-month': -1,
}

function pad(value: number): string {
  return String(value).padStart(2, '0')
}

function isoDate(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

// The calendar month `monthOffset` months from `clock`'s month (0 = the month `clock`
// falls in, -1 = the one before). Built from `clock`'s LOCAL year and month only — never
// its day-of-month — so the picker's answer does not depend on which day it opened on.
// Every arithmetic step stays in local time deliberately: Uzbekistan is UTC+5, and a
// report opened near local midnight must land in the month the viewer is actually in,
// not whatever month UTC still reads at that instant.
function monthBounds(clock: Date, monthOffset: number): PeriodRange {
  const year = clock.getFullYear()
  const month = clock.getMonth() + monthOffset
  const start = new Date(year, month, 1)
  const end = new Date(year, month + 1, 0)

  return { from: isoDate(start), to: isoDate(end) }
}

// The shared period state behind the picker and the summary report. Takes the clock as
// an argument (defaulting to `new Date()` only for real use) so a test can pin "now" and
// assert an exact date instead of a moving target.
export function usePeriod(clock: Date = new Date()): Period {
  const [preset, setPresetState] = useState<PeriodPreset>('this-month')
  const [range, setRangeState] = useState<PeriodRange>(() => monthBounds(clock, 0))

  function setPreset(next: 'this-month' | 'last-month'): void {
    setPresetState(next)
    setRangeState(monthBounds(clock, MONTH_OFFSET[next]))
  }

  function setRange(from: string, to: string): void {
    setPresetState('custom')
    setRangeState({ from, to })
  }

  return { ...range, preset, setPreset, setRange }
}
