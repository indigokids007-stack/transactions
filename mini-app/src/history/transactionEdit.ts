// The values one edit sheet holds, and the diff that turns them into a `PATCH` body
// carrying only what actually changed. Split out of `TransactionSheet` the same way
// `entryDefaults.ts`/`entryErrors.ts` are split out of `useEntryForm` — a distinct
// concern (what changed) from the component that renders the fields.
//
// Amount is deliberately not editable here: the transaction's own `amount` field is
// already formatted for display (`"10.00"` for a two-decimal currency), and running a
// formatted amount back through `parseAmount`'s grammar — which reads `.` as a
// thousands separator, never a decimal point — would silently produce a different
// minor-unit amount than the one being displayed. That mismatch is exactly the
// client/server amount-grammar drift the task brief calls out as the one risk worth
// naming; leaving amount out of this form avoids it rather than papering over it.
import type { ApiTransaction, TransactionWrite } from '../api/types'

export type TransactionEditValues = {
  type: 'income' | 'expense'
  currency: string
  categoryId: number
  note: string
  occurredOn: string
  dimensionValues: Record<number, number>
}

export function valuesFromTransaction(transaction: ApiTransaction): TransactionEditValues {
  return {
    type: transaction.type,
    currency: transaction.currency,
    categoryId: transaction.category.id,
    note: transaction.note ?? '',
    occurredOn: transaction.occurred_on,
    dimensionValues: Object.fromEntries(
      transaction.dimension_values.map((value) => [value.dimension_id, value.value_id]),
    ),
  }
}

function dimensionsEqual(a: Record<number, number>, b: Record<number, number>): boolean {
  const keys = new Set([...Object.keys(a), ...Object.keys(b)])
  return [...keys].every((key) => a[Number(key)] === b[Number(key)])
}

// Only the fields that actually changed reach the server: an edit sheet opened and
// closed without touching anything sends an empty `PATCH` body's worth of nothing, and
// a note-only edit never re-sends the category, type or dimension picks alongside it.
export function diffValues(original: TransactionEditValues, current: TransactionEditValues): TransactionWrite {
  const changes: TransactionWrite = {}

  if (current.type !== original.type) changes.type = current.type
  if (current.currency !== original.currency) changes.currency = current.currency
  if (current.categoryId !== original.categoryId) changes.category_id = current.categoryId
  if (current.note !== original.note) changes.note = current.note === '' ? null : current.note
  if (current.occurredOn !== original.occurredOn) changes.occurred_on = current.occurredOn
  if (!dimensionsEqual(current.dimensionValues, original.dimensionValues)) {
    changes.dimension_values = current.dimensionValues
  }

  return changes
}

// Reads the server's own refusal message off a rejected save or delete — duck-typed the
// way `entryErrors.ts`'s `readStatus`/`readErrors` read a rejection, so the real
// `ApiError` and a plain `{ status, message }` test double both work. A 403 or 404's
// `message` (from Laravel's own `{"message": "..."}` body, via `extractMessage`) reaches
// the user verbatim: there is no client-side guess here about *why* the server refused.
export function readMessage(error: unknown, fallback: string): string {
  if (typeof error === 'object' && error !== null && 'message' in error) {
    const message = (error as { message?: unknown }).message
    if (typeof message === 'string') return message
  }
  return fallback
}
