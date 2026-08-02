import type { ApiCategory } from '../api/types'

// Depth-first flatten with no `applies_to` filter: unlike `flattenCategoriesForType` below
// (which only offers categories valid for one transaction type), the history filter needs
// every category a transaction could already be filed under, income or expense alike, since
// it filters *across* both types at once.
export function flattenCategories(categories: ApiCategory[]): ApiCategory[] {
  return categories.flatMap((category) => [category, ...flattenCategories(category.children)])
}

// Depth-first flatten, keeping only categories (parent or child) whose `applies_to` accepts
// `type`. Mirrors `CategoryChips`' own `flatten`: a parent that doesn't apply still surfaces
// its applicable children, since `applies_to` is a leaf-level fact, not inherited. Used by the
// edit sheet, where the category picked must stay valid for whichever type is currently
// selected in that same sheet.
export function flattenCategoriesForType(categories: ApiCategory[], type: 'income' | 'expense'): ApiCategory[] {
  return categories.flatMap((category) => {
    const children = flattenCategoriesForType(category.children, type)
    const applies = category.applies_to === 'both' || category.applies_to === type
    return applies ? [category, ...children] : children
  })
}
