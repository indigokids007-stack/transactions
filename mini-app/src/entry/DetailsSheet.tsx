import type { ApiDimension } from '../api/types'
import { strings } from '../strings'
import { Sheet } from '../ui/Sheet'
import type { EntryValues } from './useEntryForm'

type DetailsSheetProps = {
  open: boolean
  onClose: () => void
  values: EntryValues
  dimensions: ApiDimension[]
  currencies: Record<string, number>
  onCurrencyChange: (currency: string) => void
  onDateChange: (date: string) => void
  onNoteChange: (note: string) => void
  onDimensionChange: (dimensionId: number, valueId: number) => void
}

const fieldRowStyle = (warn: boolean): { background: string; borderRadius: number; padding: string } => ({
  background: warn ? 'var(--field-warn)' : 'var(--field)',
  borderRadius: 18,
  padding: '14px 16px',
})

const labelStyle = { font: '600 12px/1 "Plus Jakarta Sans"', color: 'var(--muted)' }
// 16px, not the design's 14px: iOS Safari zooms the whole page on focus for any input
// under 16px, and every field here is a real `<input>`/`<select>` a thumb can tap.
const valueStyle = { border: 0, background: 'transparent', font: '700 16px/1 "Plus Jakarta Sans"', color: 'var(--teal-900)', outline: 'none', textAlign: 'right' as const }

// Currency, date, note, and one row per active dimension — everything the keypad and the
// category chips don't cover. Type moved to `EntryScreen`'s gradient header (income
// entries no longer need this sheet at all). Controlled entirely by the caller: no local
// open state, no force-open-on-missing-required — the coral dot and the disabled Saqlash
// on `EntryScreen`'s trigger button communicate that guarantee instead (see its own
// comment for why).
export function DetailsSheet({
  open,
  onClose,
  values,
  dimensions,
  currencies,
  onCurrencyChange,
  onDateChange,
  onNoteChange,
  onDimensionChange,
}: DetailsSheetProps) {
  return (
    <Sheet label={strings.entry.details} open={open} onClose={onClose}>
      <label className="flex items-center justify-between" style={fieldRowStyle(false)}>
        <span style={labelStyle}>{strings.entry.currency}</span>
        <select value={values.currency} onChange={(event) => onCurrencyChange(event.target.value)} style={valueStyle}>
          {Object.keys(currencies).map((code) => (
            <option key={code} value={code}>
              {code}
            </option>
          ))}
        </select>
      </label>

      <label className="flex items-center justify-between" style={fieldRowStyle(false)}>
        <span style={labelStyle}>{strings.entry.date}</span>
        <input type="date" value={values.occurredOn} onChange={(event) => onDateChange(event.target.value)} style={valueStyle} />
      </label>

      <label className="block" style={fieldRowStyle(false)}>
        <span style={labelStyle}>{strings.entry.note}</span>
        <input
          type="text"
          value={values.note}
          onChange={(event) => onNoteChange(event.target.value)}
          className="mt-2 block w-full"
          style={{ border: 0, background: 'transparent', font: '600 16px/1 "Plus Jakarta Sans"', color: 'var(--ink)', outline: 'none' }}
        />
      </label>

      {dimensions.map((dimension) => {
        const warn = values.dimensionValues[dimension.id] === undefined && dimension.is_required
        return (
          <label key={dimension.id} className="flex items-center justify-between" style={fieldRowStyle(warn)}>
            <span style={labelStyle}>{dimension.name}</span>
            <select
              value={values.dimensionValues[dimension.id] ?? ''}
              onChange={(event) => onDimensionChange(dimension.id, Number(event.target.value))}
              style={valueStyle}
            >
              <option value="" disabled>
                {strings.entry.choose}
              </option>
              {dimension.values.map((value) => (
                <option key={value.id} value={value.id}>
                  {value.name}
                </option>
              ))}
            </select>
          </label>
        )
      })}

      <button
        type="button"
        onClick={onClose}
        className="w-full"
        style={{
          marginTop: 8,
          border: 0,
          borderRadius: 22,
          padding: 17,
          background: 'var(--grad-action)',
          color: '#fff',
          font: '700 15px/1 "Plus Jakarta Sans"',
          boxShadow: 'var(--shadow-action)',
        }}
      >
        {strings.entry.done}
      </button>
    </Sheet>
  )
}
