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
export function TransactionFields({ values, bootstrap, onChange }: TransactionFieldsProps) {
  return (
    <>
      <div role="group" aria-label={strings.entry.type} className="flex gap-2">
        {TYPES.map((type) => (
          <button
            key={type}
            type="button"
            aria-pressed={values.type === type}
            onClick={() => onChange({ type })}
            className="rounded-full px-3 py-1 text-sm"
            style={{
              background: values.type === type ? 'var(--accent)' : 'var(--tg-secondary-bg)',
              color: values.type === type ? 'var(--accent-text)' : 'var(--tg-text)',
            }}
          >
            {strings.entry[type]}
          </button>
        ))}
      </div>

      <label className="block text-sm">
        {strings.entry.category}
        <select
          value={values.categoryId}
          onChange={(event) => onChange({ categoryId: Number(event.target.value) })}
          className="mt-1 block w-full rounded border px-2 py-1"
        >
          {flattenCategories(bootstrap.categories).map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </select>
      </label>

      <label className="block text-sm">
        {strings.entry.date}
        <input
          type="date"
          value={values.occurredOn}
          onChange={(event) => onChange({ occurredOn: event.target.value })}
          className="mt-1 block w-full rounded border px-2 py-1"
        />
      </label>

      <label className="block text-sm">
        {strings.entry.note}
        <input
          type="text"
          value={values.note}
          onChange={(event) => onChange({ note: event.target.value })}
          className="mt-1 block w-full rounded border px-2 py-1"
        />
      </label>

      {bootstrap.dimensions.map((dimension) => (
        <label key={dimension.id} className="block text-sm">
          {dimension.name}
          <select
            value={values.dimensionValues[dimension.id] ?? ''}
            onChange={(event) =>
              onChange({ dimensionValues: { ...values.dimensionValues, [dimension.id]: Number(event.target.value) } })
            }
            className="mt-1 block w-full rounded border px-2 py-1"
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
    </>
  )
}
