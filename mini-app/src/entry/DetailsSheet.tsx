import { useState } from 'react'
import type { ApiDimension } from '../api/types'
import { strings } from '../strings'
import { Sheet } from '../ui/Sheet'
import type { EntryValues } from './useEntryForm'

type DetailsSheetProps = {
  values: EntryValues
  dimensions: ApiDimension[]
  currencies: Record<string, number>
  missingRequired: string[]
  onTypeChange: (type: 'income' | 'expense') => void
  onCurrencyChange: (currency: string) => void
  onDateChange: (date: string) => void
  onNoteChange: (note: string) => void
  onDimensionChange: (dimensionId: number, valueId: number) => void
}

const TYPES = ['expense', 'income'] as const

// Type, currency, date, note and the dimension picks: everything the keypad and the
// category chips don't cover. Collapsed by default so the keypad keeps the room, but a
// required dimension with no answer forces it open — derived at render from
// `missingRequired`, not synced through an effect.
export function DetailsSheet({
  values,
  dimensions,
  currencies,
  missingRequired,
  onTypeChange,
  onCurrencyChange,
  onDateChange,
  onNoteChange,
  onDimensionChange,
}: DetailsSheetProps) {
  const [manuallyOpened, setManuallyOpened] = useState(false)
  const open = manuallyOpened || missingRequired.length > 0

  return (
    <Sheet label={strings.entry.details} open={open} onToggle={() => setManuallyOpened((current) => !current)}>
      <div role="group" aria-label={strings.entry.type} className="flex gap-2">
        {TYPES.map((type) => (
          <button
            key={type}
            type="button"
            aria-pressed={values.type === type}
            onClick={() => onTypeChange(type)}
            className="rounded-full px-3 py-1 text-sm"
            style={{
              background: values.type === type ? 'var(--tg-button)' : 'var(--tg-secondary-bg)',
              color: values.type === type ? 'var(--tg-button-text)' : 'var(--tg-text)',
            }}
          >
            {strings.entry[type]}
          </button>
        ))}
      </div>

      <label className="block text-sm">
        {strings.entry.currency}
        <select
          value={values.currency}
          onChange={(event) => onCurrencyChange(event.target.value)}
          className="mt-1 block w-full rounded border px-2 py-1"
        >
          {Object.keys(currencies).map((code) => (
            <option key={code} value={code}>
              {code}
            </option>
          ))}
        </select>
      </label>

      <label className="block text-sm">
        {strings.entry.date}
        <input
          type="date"
          value={values.occurredOn}
          onChange={(event) => onDateChange(event.target.value)}
          className="mt-1 block w-full rounded border px-2 py-1"
        />
      </label>

      <label className="block text-sm">
        {strings.entry.note}
        <input
          type="text"
          value={values.note}
          onChange={(event) => onNoteChange(event.target.value)}
          className="mt-1 block w-full rounded border px-2 py-1"
        />
      </label>

      {dimensions.map((dimension) => (
        <label key={dimension.id} className="block text-sm">
          {dimension.name}
          <select
            value={values.dimensionValues[dimension.id] ?? ''}
            onChange={(event) => onDimensionChange(dimension.id, Number(event.target.value))}
            className="mt-1 block w-full rounded border px-2 py-1"
          >
            <option value="" disabled>
              …
            </option>
            {dimension.values.map((value) => (
              <option key={value.id} value={value.id}>
                {value.name}
              </option>
            ))}
          </select>
        </label>
      ))}
    </Sheet>
  )
}
