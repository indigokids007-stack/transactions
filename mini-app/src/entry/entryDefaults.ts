// The blank-slate values a new entry starts from — split out of `useEntryForm` since
// "what a fresh form looks like" is a distinct concern from the state that changes it,
// and both the initial `useState` and every post-save reset need the same answer.
import type { Bootstrap } from '../api/types'

export type EntryValues = {
  type: 'income' | 'expense'
  currency: string
  categoryId: number | null
  dimensionValues: Record<number, number>
  amountInput: string
  note: string
  occurredOn: string
}

function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}

export function valuesFromDefaults(bootstrap: Bootstrap): EntryValues {
  return {
    type: bootstrap.defaults.type,
    currency: bootstrap.defaults.currency,
    categoryId: bootstrap.defaults.category_id,
    dimensionValues: { ...bootstrap.defaults.dimension_values },
    amountInput: '',
    note: '',
    occurredOn: todayIso(),
  }
}
