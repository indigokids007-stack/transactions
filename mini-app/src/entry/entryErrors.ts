// Reading a save failure and deciding what a stale 422 means: split out of `useEntryForm`
// so that file stays about state, not about interpreting the shape of an error.
import type { ApiClient } from '../api/client'
import type { Bootstrap } from '../api/types'

export type FieldErrors = Record<string, string[]>

// Duck-typed the way `useSession`'s `toRejectedState` reads a rejection: the real
// `ApiError` carries `status`/`errors`, and tests stand in a plain object of the same
// shape, so neither an `instanceof` check nor a specific error class should be required.
export function readStatus(error: unknown): number | undefined {
  if (typeof error === 'object' && error !== null && 'status' in error) {
    const status = (error as { status?: unknown }).status
    return typeof status === 'number' ? status : undefined
  }
  return undefined
}

export function readErrors(error: unknown): FieldErrors | undefined {
  if (typeof error !== 'object' || error === null || !('errors' in error)) return undefined

  const errors = (error as { errors?: unknown }).errors
  if (typeof errors !== 'object' || errors === null) return undefined
  if (!Object.values(errors).every((messages) => Array.isArray(messages))) return undefined

  return errors as FieldErrors
}

// A 422 naming either of these means a reference row (the category, or a dimension's
// value) was deactivated after bootstrap loaded: retrying the same pick can never
// succeed, so these get a refetch-and-reset instead of an inline field message.
export function staleCategory(errors: FieldErrors): boolean {
  return 'category_id' in errors
}

export function staleDimensions(errors: FieldErrors): boolean {
  return 'dimension_values' in errors
}

export function isStaleReference(errors: FieldErrors): boolean {
  return staleCategory(errors) || staleDimensions(errors)
}

export type StaleReferenceResolution = {
  reference: Bootstrap
  categoryId: number | null
  dimensionValues: Record<number, number>
}

// Refetches the reference data and decides which of the user's current picks survive:
// only the field(s) the 422 actually named as stale are cleared, so an amount or note
// already typed is never wiped along with a category that just needs re-picking.
export async function resolveStaleReference(
  errors: FieldErrors,
  client: Pick<ApiClient, 'bootstrap'>,
  current: { categoryId: number | null; dimensionValues: Record<number, number> },
): Promise<StaleReferenceResolution> {
  const reference = await client.bootstrap()

  return {
    reference,
    categoryId: staleCategory(errors) ? reference.defaults.category_id : current.categoryId,
    dimensionValues: staleDimensions(errors) ? {} : current.dimensionValues,
  }
}
