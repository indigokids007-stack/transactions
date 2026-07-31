import { render, screen } from '@testing-library/react'
import { DetailsSheet } from './DetailsSheet'
import type { EntryValues } from './useEntryForm'
import { bootstrapFixture } from '../test/fixtures'

function values(overrides: Partial<EntryValues> = {}): EntryValues {
  return {
    type: 'expense',
    currency: 'UZS',
    categoryId: 7,
    dimensionValues: {},
    amountInput: '',
    note: '',
    occurredOn: '2026-07-31',
    ...overrides,
  }
}

const noop = () => {}

it('stays open once a required dimension has forced it open, even after the dimension is answered', () => {
  const { rerender } = render(
    <DetailsSheet
      values={values()}
      dimensions={bootstrapFixture.dimensions}
      currencies={bootstrapFixture.currencies}
      missingRequired={['Filial']}
      onTypeChange={noop}
      onCurrencyChange={noop}
      onDateChange={noop}
      onNoteChange={noop}
      onDimensionChange={noop}
    />,
  )

  expect(screen.getByLabelText('Filial')).toBeVisible()

  // Simulates the parent re-rendering the instant the user picks a value: `missingRequired`
  // drops to empty on the very next render, the way it does in the real form.
  rerender(
    <DetailsSheet
      values={values({ dimensionValues: { 3: 9 } })}
      dimensions={bootstrapFixture.dimensions}
      currencies={bootstrapFixture.currencies}
      missingRequired={[]}
      onTypeChange={noop}
      onCurrencyChange={noop}
      onDateChange={noop}
      onNoteChange={noop}
      onDimensionChange={noop}
    />,
  )

  expect(screen.getByLabelText('Filial')).toBeVisible()
})
