import type { Bootstrap } from '../api/types'
import { strings } from '../strings'
import { flattenCategories } from './historyCategories'
import type { TransactionEditValues } from './transactionEdit'

type TransactionFieldsProps = {
  values: TransactionEditValues
  bootstrap: Bootstrap
  onChange: (next: Partial<TransactionEditValues>) => void
}

const TYPES = ['expense', 'income'] as const

// The editable fields of one transaction — type, category, date, note, and every active
// dimension — split out of `TransactionSheet` purely to keep that file's line count
// down, the same way `DetailsSheet` holds the entry form's own fields separately from
// `EntryScreen`. Amount and currency are not here: see `transactionEdit.ts`'s doc comment
// for why — the currency control is `TransactionSheet`'s read-only header instead.
const rowStyle = { background: 'var(--field)', borderRadius: 18, padding: '14px 16px' }
const labelStyle = { font: '600 12px/1 "Plus Jakarta Sans"', color: 'var(--muted)' }
const valueStyle = { border: 0, background: 'transparent', font: '700 14px/1 "Plus Jakarta Sans"', color: 'var(--teal-900)', outline: 'none', textAlign: 'right' as const }

export function TransactionFields({ values, bootstrap, onChange }: TransactionFieldsProps) {
  return (
    <div className="flex flex-col gap-[9px]">
      <div role="group" aria-label={strings.entry.type} className="flex gap-2">
        {TYPES.map((type) => (
          <button
            key={type}
            type="button"
            aria-pressed={values.type === type}
            onClick={() => onChange({ type })}
            className="rounded-full"
            style={{
              border: 0,
              padding: '9px 13px',
              font: '600 12px/1 "Plus Jakarta Sans"',
              background: values.type === type ? 'var(--teal-900)' : 'var(--pill-bg)',
              color: values.type === type ? '#fff' : 'var(--ink-2)',
            }}
          >
            {strings.entry[type]}
          </button>
        ))}
      </div>

      <label className="flex items-center justify-between" style={rowStyle}>
        <span style={labelStyle}>{strings.entry.category}</span>
        <select
          value={values.categoryId}
          onChange={(event) => onChange({ categoryId: Number(event.target.value) })}
          style={valueStyle}
        >
          {flattenCategories(bootstrap.categories).map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </select>
      </label>

      <label className="flex items-center justify-between" style={rowStyle}>
        <span style={labelStyle}>{strings.entry.date}</span>
        <input
          type="date"
          value={values.occurredOn}
          onChange={(event) => onChange({ occurredOn: event.target.value })}
          style={valueStyle}
        />
      </label>

      <label className="block" style={rowStyle}>
        <span style={labelStyle}>{strings.entry.note}</span>
        <input
          type="text"
          value={values.note}
          onChange={(event) => onChange({ note: event.target.value })}
          className="mt-2 block w-full"
          style={{ border: 0, background: 'transparent', font: '600 14px/1 "Plus Jakarta Sans"', color: 'var(--ink)', outline: 'none' }}
        />
      </label>

      {bootstrap.dimensions.map((dimension) => (
        <label key={dimension.id} className="flex items-center justify-between" style={rowStyle}>
          <span style={labelStyle}>{dimension.name}</span>
          <select
            value={values.dimensionValues[dimension.id] ?? ''}
            onChange={(event) =>
              onChange({ dimensionValues: { ...values.dimensionValues, [dimension.id]: Number(event.target.value) } })
            }
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
      ))}
    </div>
  )
}
