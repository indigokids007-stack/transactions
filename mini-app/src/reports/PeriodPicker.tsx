import { strings } from '../strings'
import type { Period } from './usePeriod'

type PeriodPickerProps = {
  period: Period
}

const PRESETS = ['this-month', 'last-month'] as const

// The two presets a report reader reaches for first. A custom range is part of
// `usePeriod`'s contract for later callers (filters, exports), but nothing here needs a
// date-range input yet, so this picker doesn't speculate one into existence.
export function PeriodPicker({ period }: PeriodPickerProps) {
  return (
    <div role="group" aria-label={strings.reports.period} className="flex gap-2 px-4 py-2">
      {PRESETS.map((preset) => {
        const selected = period.preset === preset
        return (
          <button
            key={preset}
            type="button"
            aria-pressed={selected}
            onClick={() => period.setPreset(preset)}
            className="rounded-full px-3 py-1 text-sm"
            style={{
              background: selected ? 'var(--tg-button)' : 'var(--tg-secondary-bg)',
              color: selected ? 'var(--tg-button-text)' : 'var(--tg-text)',
            }}
          >
            {strings.reports[preset === 'this-month' ? 'thisMonth' : 'lastMonth']}
          </button>
        )
      })}
    </div>
  )
}
