import { describe, expect, it } from 'vitest'
import type { ApiCategory } from '../api/types'
import { flattenCategories, flattenCategoriesForType } from './historyCategories'

const categories: ApiCategory[] = [
  {
    id: 1,
    name: 'Taxi',
    applies_to: 'expense',
    children: [],
  },
  {
    id: 2,
    name: 'Salary',
    applies_to: 'income',
    children: [],
  },
  {
    id: 3,
    name: 'Misc',
    applies_to: 'both',
    children: [{ id: 4, name: 'Misc child', applies_to: 'expense', children: [] }],
  },
]

describe('flattenCategories', () => {
  it('keeps every category regardless of applies_to', () => {
    expect(flattenCategories(categories).map((category) => category.id)).toEqual([1, 2, 3, 4])
  })
})

describe('flattenCategoriesForType', () => {
  it('keeps only categories that apply to expense, including applicable children of a non-applying parent', () => {
    expect(flattenCategoriesForType(categories, 'expense').map((category) => category.id)).toEqual([1, 3, 4])
  })

  it('keeps only categories that apply to income', () => {
    expect(flattenCategoriesForType(categories, 'income').map((category) => category.id)).toEqual([2, 3])
  })
})
