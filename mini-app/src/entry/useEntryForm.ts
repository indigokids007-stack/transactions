import { useRef, useState } from 'react'
import { parseAmount } from './parseAmount'
import type { ApiClient } from '../api/client'
import type { ApiTransaction, Bootstrap, TransactionWrite } from '../api/types'
import { strings } from '../strings'

export type EntryValues = {
  type: 'income' | 'expense'
  currency: string
  categoryId: number | null
  dimensionValues: Record<number, number>
  amountInput: string
  note: string
  occurredOn: string
}

export type EntryForm = {
  values: EntryValues
  categories: Bootstrap['categories']
  dimensions: Bootstrap['dimensions']
  currencies: Bootstrap['currencies']
  setAmount: (input: string) => void
  setCategory: (id: number) => void
  setDimension: (dimensionId: number, valueId: number) => void
  setType: (type: 'income' | 'expense') => void
  setCurrency: (currency: string) => void
  setNote: (note: string) => void
  setDate: (date: string) => void
  canSave: boolean
  missingRequired: string[]
  save: () => Promise<void>
  lastSaved: ApiTransaction | null
  undo: () => Promise<void>
  fieldErrors: Record<string, string[]>
  notice: string | null
}

// The keys a 422 can name that mean a reference row (the category, or a dimension's
// value) was deactivated after bootstrap loaded: retrying the same pick can never
// succeed, so these get a refetch-and-reset instead of an inline field message.
const STALE_REFERENCE_FIELDS = ['category_id', 'dimension_values'] as const

function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}

function valuesFromDefaults(bootstrap: Bootstrap): EntryValues {
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

// Duck-typed the way `useSession`'s `toRejectedState` reads a rejection: the real
// `ApiError` carries `status`/`errors`, and tests stand in a plain object of the same
// shape, so neither an `instanceof` check nor a specific error type should be required.
function readStatus(error: unknown): number | undefined {
  if (typeof error === 'object' && error !== null && 'status' in error) {
    const status = (error as { status?: unknown }).status
    return typeof status === 'number' ? status : undefined
  }
  return undefined
}

function readErrors(error: unknown): Record<string, string[]> | undefined {
  if (typeof error !== 'object' || error === null || !('errors' in error)) return undefined

  const errors = (error as { errors?: unknown }).errors
  if (typeof errors !== 'object' || errors === null) return undefined
  if (!Object.values(errors).every((messages) => Array.isArray(messages))) return undefined

  return errors as Record<string, string[]>
}

export function useEntryForm(bootstrap: Bootstrap, client: ApiClient): EntryForm {
  const [reference, setReference] = useState(bootstrap)
  const [values, setValues] = useState<EntryValues>(() => valuesFromDefaults(bootstrap))
  const [lastSaved, setLastSaved] = useState<ApiTransaction | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [notice, setNotice] = useState<string | null>(null)
  // Written only inside `save`, never during render: the same key must survive a retry
  // of the same attempt, and a `ref` is the escape hatch for state a render doesn't own.
  const idempotencyKeyRef = useRef<string | null>(null)

  const parsedAmount = parseAmount(values.amountInput)
  const missingRequired = reference.dimensions
    .filter((dimension) => dimension.is_required && values.dimensionValues[dimension.id] === undefined)
    .map((dimension) => dimension.name)
  const canSave = parsedAmount !== null && missingRequired.length === 0

  function setAmount(input: string): void {
    setValues((current) => ({ ...current, amountInput: input }))
  }

  function setCategory(id: number): void {
    setValues((current) => ({ ...current, categoryId: id }))
  }

  function setDimension(dimensionId: number, valueId: number): void {
    setValues((current) => ({
      ...current,
      dimensionValues: { ...current.dimensionValues, [dimensionId]: valueId },
    }))
  }

  function setType(type: 'income' | 'expense'): void {
    setValues((current) => ({ ...current, type }))
  }

  function setCurrency(currency: string): void {
    setValues((current) => ({ ...current, currency }))
  }

  function setNote(note: string): void {
    setValues((current) => ({ ...current, note }))
  }

  function setDate(date: string): void {
    setValues((current) => ({ ...current, occurredOn: date }))
  }

  async function handleStaleReference(errors: Record<string, string[]>): Promise<void> {
    const refreshed = await client.bootstrap()
    setReference(refreshed)
    setNotice(strings.entry.referenceChanged)
    setValues((current) => ({
      ...current,
      categoryId: 'category_id' in errors ? refreshed.defaults.category_id : current.categoryId,
      dimensionValues: 'dimension_values' in errors ? {} : current.dimensionValues,
    }))
  }

  async function save(): Promise<void> {
    if (!canSave || parsedAmount === null) return

    setFieldErrors({})
    setNotice(null)

    idempotencyKeyRef.current ??= crypto.randomUUID()
    const key = idempotencyKeyRef.current

    const body: TransactionWrite = {
      type: values.type,
      amount: parsedAmount.amount,
      currency: values.currency,
      occurred_on: values.occurredOn,
      category_id: values.categoryId ?? undefined,
      note: values.note === '' ? null : values.note,
      dimension_values: values.dimensionValues,
    }

    try {
      const response = await client.createTransaction(body, key)
      idempotencyKeyRef.current = null
      setLastSaved(response.data)
      setValues(valuesFromDefaults(reference))
    } catch (error) {
      const status = readStatus(error)
      const errors = readErrors(error)

      if (status === 422 && errors) {
        setFieldErrors(errors)

        if (STALE_REFERENCE_FIELDS.some((field) => field in errors)) {
          await handleStaleReference(errors)
        }
        return
      }

      throw error
    }
  }

  async function undo(): Promise<void> {
    if (lastSaved === null) return
    await client.deleteTransaction(lastSaved.id)
    setLastSaved(null)
  }

  return {
    values,
    categories: reference.categories,
    dimensions: reference.dimensions,
    currencies: reference.currencies,
    setAmount,
    setCategory,
    setDimension,
    setType,
    setCurrency,
    setNote,
    setDate,
    canSave,
    missingRequired,
    save,
    lastSaved,
    undo,
    fieldErrors,
    notice,
  }
}
