import type { ApiDimension, Bootstrap } from '../api/types'
import { strings } from '../strings'
import { PeriodPicker } from '../reports/PeriodPicker'
import type { Period } from '../reports/usePeriod'
import { flattenCategories } from './historyCategories'

export type FiltersProps = {
  period: Period
  bootstrap: Bootstrap
  categoryId: number | undefined
  onCategoryChange: (id: number | undefined) => void
  currency: string | undefined
  onCurrencyChange: (currency: string | undefined) => void
  dimensionValues: Record<string, number>
  onDimensionChange: (dimension: ApiDimension, valueId: number | undefined) => void
}

function toId(raw: string): number | undefined {
  return raw === '' ? undefined : Number(raw)
}

// The history list's own filter row: the period picker it shares with Reports, plus
// category, currency and one select per active dimension, all fed from the bootstrap
// payload rather than a second network call. Purely controlled — `HistoryScreen` owns
// every value and turns a change here into the `TransactionListParams` shape the API
// expects, including the `dimension[<key>]` bracket form (`useTransactions`'s concern,
// not this component's).
export function Filters({
  period,
  bootstrap,
  categoryId,
  onCategoryChange,
  currency,
  onCurrencyChange,
  dimensionValues,
  onDimensionChange,
}: FiltersProps) {
  return (
    <div
      className="mx-4 flex flex-col gap-3 rounded-xl border p-4 shadow-sm"
      style={{ background: 'var(--surface-card)', borderColor: 'var(--border)' }}
    >
      <PeriodPicker period={period} />

      <label className="block text-sm">
        {strings.history.category}
        <select
          value={categoryId ?? ''}
          onChange={(event) => onCategoryChange(toId(event.target.value))}
          className="mt-1 block w-full rounded border px-2 py-1"
        >
          <option value="">{strings.history.allCategories}</option>
          {flattenCategories(bootstrap.categories).map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </select>
      </label>

      <label className="block text-sm">
        {strings.history.currency}
        <select
          value={currency ?? ''}
          onChange={(event) => onCurrencyChange(event.target.value === '' ? undefined : event.target.value)}
          className="mt-1 block w-full rounded border px-2 py-1"
        >
          <option value="">{strings.history.allCurrencies}</option>
          {Object.keys(bootstrap.currencies).map((code) => (
            <option key={code} value={code}>
              {code}
            </option>
          ))}
        </select>
      </label>

      {bootstrap.dimensions.map((dimension) => (
        <label key={dimension.id} className="block text-sm">
          {dimension.name}
          <select
            value={dimensionValues[dimension.key] ?? ''}
            onChange={(event) => onDimensionChange(dimension, toId(event.target.value))}
            className="mt-1 block w-full rounded border px-2 py-1"
          >
            <option value="">{strings.history.allValues}</option>
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
