import { describe, expect, it } from 'vitest'
import { foldGroupsToSlots } from './CurrencySection'
import type { GroupRow } from './CurrencySection'

function row(label: string, amount_minor: number): GroupRow {
  return {
    key: label,
    label,
    currency: 'UZS',
    type: 'expense',
    amount_minor,
    amount: String(amount_minor),
    count: 1,
  }
}

describe('foldGroupsToSlots', () => {
  it('returns groups unchanged when there are 8 or fewer', () => {
    const groups = [row('A', 100), row('B', 200)]
    expect(foldGroupsToSlots(groups, 'Boshqa')).toEqual(groups)
  })

  it('returns an empty array for an empty input', () => {
    expect(foldGroupsToSlots([], 'Boshqa')).toEqual([])
  })

  it('keeps exactly 8 groups unchanged when there are exactly 8', () => {
    const groups = Array.from({ length: 8 }, (_, i) => row(`G${i}`, i + 1))
    expect(foldGroupsToSlots(groups, 'Boshqa')).toEqual(groups)
  })

  it('folds the 9th+ group into one "Other" slice, keeping the first 7 untouched', () => {
    const groups = [
      row('A', 500),
      row('B', 400),
      row('C', 300),
      row('D', 200),
      row('E', 150),
      row('F', 120),
      row('G', 100),
      row('H', 80),
      row('I', 20),
    ]

    const result = foldGroupsToSlots(groups, 'Boshqa')

    expect(result).toHaveLength(8)
    expect(result.slice(0, 7)).toEqual(groups.slice(0, 7))
    expect(result[7].label).toBe('Boshqa')
    // H (80) + I (20) folded together.
    expect(result[7].amount_minor).toBe(100)
    expect(result[7].count).toBe(2)
    expect(result[7].currency).toBe('UZS')
  })
})
