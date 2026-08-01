import { useEffect, useState } from 'react'
import { strings } from '../strings'
import type { Period } from './usePeriod'

type PeriodPickerProps = {
  period: Period
}

const PRESETS = ['this-month', 'last-month'] as const

function chipStyle(selected: boolean): { background: string; color: string } {
  return {
    background: selected ? 'var(--accent)' : 'var(--tg-secondary-bg)',
    color: selected ? 'var(--tg-button-text)' : 'var(--tg-text)',
  }
}

// The approved picker: "this month, last month, custom range" (Task 8's design), shared
// by Reports and History since both take the same `Period`. The custom fields keep their
// own local from/to so every keystroke can be validated before it ever reaches
// `period.setRange` — an end before its start is refused right here, so a report view
// downstream never sees a range that would only tell it "nothing here" for the wrong
// reason, and no request goes out for a range that makes no sense.
export function PeriodPicker({ period }: PeriodPickerProps) {
  const [customFrom, setCustomFrom] = useState(period.from)
  const [customTo, setCustomTo] = useState(period.to)

  // Mirrors whichever preset is active back into the custom fields, so switching to
  // "custom" right after picking "last month" starts from last month's own dates rather
  // than whatever the fields held before. Only syncs while a preset — not custom — is
  // active: once custom is selected, `period.from`/`period.to` only ever change because
  // `applyCustomRange` below already pushed these same fields into `period.setRange`, so
  // there is nothing external left to mirror.
  useEffect(() => {
    if (period.preset !== 'custom') {
      setCustomFrom(period.from)
      setCustomTo(period.to)
    }
  }, [period.preset, period.from, period.to])

  const invalidRange = customFrom > customTo

  function applyCustomRange(from: string, to: string): void {
    setCustomFrom(from)
    setCustomTo(to)
    if (from <= to) period.setRange(from, to)
  }

  return (
    <div className="flex flex-col gap-2 px-4 py-2">
      <div role="group" aria-label={strings.reports.period} className="flex gap-2">
        {PRESETS.map((preset) => {
          const selected = period.preset === preset
          return (
            <button
              key={preset}
              type="button"
              aria-pressed={selected}
              onClick={() => period.setPreset(preset)}
              className="rounded-full px-3 py-1 text-sm"
              style={chipStyle(selected)}
            >
              {strings.reports[preset === 'this-month' ? 'thisMonth' : 'lastMonth']}
            </button>
          )
        })}
        <button
          type="button"
          aria-pressed={period.preset === 'custom'}
          onClick={() => applyCustomRange(customFrom, customTo)}
          className="rounded-full px-3 py-1 text-sm"
          style={chipStyle(period.preset === 'custom')}
        >
          {strings.reports.customRange}
        </button>
      </div>

      {period.preset === 'custom' && (
        <div className="flex flex-col gap-2">
          <div className="flex gap-2">
            <label className="block text-sm">
              {strings.reports.rangeFrom}
              <input
                type="date"
                value={customFrom}
                onChange={(event) => applyCustomRange(event.target.value, customTo)}
                className="mt-1 block w-full rounded border px-2 py-1"
              />
            </label>
            <label className="block text-sm">
              {strings.reports.rangeTo}
              <input
                type="date"
                value={customTo}
                onChange={(event) => applyCustomRange(customFrom, event.target.value)}
                className="mt-1 block w-full rounded border px-2 py-1"
              />
            </label>
          </div>
          {invalidRange && (
            <p role="alert" className="text-sm">
              {strings.reports.rangeInvalid}
            </p>
          )}
        </div>
      )}
    </div>
  )
}
