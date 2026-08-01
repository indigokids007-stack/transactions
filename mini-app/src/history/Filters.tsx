import type { ApiDimension, Bootstrap } from '../api/types'
import { strings } from '../strings'
import { PeriodPicker } from '../reports/PeriodPicker'
import type { Period } from '../reports/usePeriod'
import { flattenCategories } from './historyCategories'

export type FiltersProps = {
  /** Whether the category/currency/dimension selects show — the period picker itself
   * always shows. Owned by `HistoryScreen`'s "Filtrlar" toggle button. */
  open: boolean
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

const rowStyle = { background: 'var(--field)', borderRadius: 16, padding: '12px 15px' }
const labelStyle = { font: '600 12px/1 "Plus Jakarta Sans"', color: 'var(--muted)' }
const valueStyle = { border: 0, background: 'transparent', font: '700 13px/1 "Plus Jakarta Sans"', color: 'var(--teal-900)', outline: 'none', textAlign: 'right' as const }

// The history list's own filter row: the period picker it shares with Reports (always
// visible), plus category, currency and one select per active dimension (collapsible —
// `HistoryScreen`'s "Filtrlar" toggle). Purely controlled — `HistoryScreen` owns every
// value and turns a change here into the `TransactionListParams` shape the API expects,
// including the `dimension[<key>]` bracket form (`useTransactions`'s concern, not this
// component's).
export function Filters({
  open,
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
    <div className="flex flex-col gap-3.5" style={{ padding: '0 22px' }}>
      <PeriodPicker period={period} />

      {open ? (
        <div className="flex flex-col gap-[9px]">
          <label className="flex items-center justify-between" style={rowStyle}>
            <span style={labelStyle}>{strings.history.category}</span>
            <select
              value={categoryId ?? ''}
              onChange={(event) => onCategoryChange(toId(event.target.value))}
              style={valueStyle}
            >
              <option value="">{strings.history.allCategories}</option>
              {flattenCategories(bootstrap.categories).map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </select>
          </label>

          <label className="flex items-center justify-between" style={rowStyle}>
            <span style={labelStyle}>{strings.history.currency}</span>
            <select
              value={currency ?? ''}
              onChange={(event) => onCurrencyChange(event.target.value === '' ? undefined : event.target.value)}
              style={valueStyle}
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
            <label key={dimension.id} className="flex items-center justify-between" style={rowStyle}>
              <span style={labelStyle}>{dimension.name}</span>
              <select
                value={dimensionValues[dimension.key] ?? ''}
                onChange={(event) => onDimensionChange(dimension, toId(event.target.value))}
                style={valueStyle}
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
      ) : null}
    </div>
  )
}
