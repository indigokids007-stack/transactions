import type { ApiCategory } from '../api/types'

// Depth-first flatten with no `applies_to` filter: unlike `CategoryChips` (which only
// offers categories valid for the transaction type currently being entered), the history
// filter and the edit sheet both need every category a transaction could already be
// filed under, income or expense alike.
export function flattenCategories(categories: ApiCategory[]): ApiCategory[] {
  return categories.flatMap((category) => [category, ...flattenCategories(category.children)])
}
