import { parseAmount } from './parseAmount'

const accepted: [string, string][] = [
  ['100000', '100000'],
  ['100.000', '100000'],
  ['1.250.000', '1250000'],
  ['12.50', '12500'],
  ['12,50', '12500'],
  ['100k', '100000'],
  ['1.5k', '1500'],
  ['30 ming', '30000'],
  ['30ming', '30000'],
  ['1.5 mln', '1500000'],
  ['2 mlrd', '2000000000'],
  ['30 минг', '30000'],
  ['50к', '50000'],
  ['999999999999999', '999999999999999'],
]

const refused = [
  '100 000',
  "120'000",
  '1.000,00',
  '1.250.00',
  '12.345.6',
  '-1000',
  '1234567890123456',
  '0',
  '0k',
  '',
  'salom',
  '1..5',
  '.5',
  '5.',
]

it.each(accepted)('reads %s as %s', (input, expected) => {
  expect(parseAmount(input)).toEqual({ amount: expected })
})

it.each(refused)('refuses %s', (input) => {
  expect(parseAmount(input)).toBeNull()
})

// Every magnitude spelling, glued and spaced, in both scripts. A missing spelling is a
// live thousandfold error, so each one is asserted rather than sampled.
const thousands = [
  'k',
  'к',
  'ming',
  'mingta',
  'минг',
  'мингта',
  'тыс',
  'тысяч',
  'тысяча',
  'тыщ',
  'тыща',
  'тыщи',
]
const millions = ['mln', 'млн', 'million', 'millon', 'миллион', 'миллон', 'мильон', 'лям']
const milliards = ['mlrd', 'млрд', 'milliard', 'миллиард']

const magnitudes: [string, string][] = [
  ...thousands.map((word): [string, string] => [word, '30000']),
  ...millions.map((word): [string, string] => [word, '30000000']),
  ...milliards.map((word): [string, string] => [word, '30000000000']),
]

it.each(magnitudes)('reads 30%s and 30 %s', (word, expected) => {
  expect(parseAmount(`30${word}`)).toEqual({ amount: expected })
  expect(parseAmount(`30 ${word}`)).toEqual({ amount: expected })
  expect(parseAmount(`30${word.toUpperCase()}`)).toEqual({ amount: expected })
})

// The bound is applied to the built digit string, so a magnitude cannot overflow past it.
const boundary: [string, string][] = [
  ['999999999999.999k', '999999999999999'],
  ['0.500', '500'],
  ['007', '7'],
  ['1.5', '1500'],
]

it.each(boundary)('reads %s as %s', (input, expected) => {
  expect(parseAmount(input)).toEqual({ amount: expected })
})

// Unicode whitespace pasted from a bank app or a spreadsheet groups nothing, exactly as
// an ASCII space groups nothing; a note in the amount field is not an amount.
const refusedFurther = [
  '100 000',
  '100 000',
  '100 000',
  '100 000',
  '100　000',
  '1 000 000',
  '100 00',
  '999999999999999k',
  '999999999999.9999k',
  '1000000000000000',
  '+1000',
  '100000 taksi',
  '5000 k taksi',
  '5000 к маме',
  '50 mingga',
  '2 mln.',
  '30 тыс.',
  '1e5',
  '1,,5',
  '100000.',
  '1 mln 5 ming',
  '١٠٠',
]

it.each(refusedFurther)('refuses %s', (input) => {
  expect(parseAmount(input)).toBeNull()
})

it('ignores the whitespace around an amount', () => {
  expect(parseAmount('  100000  ')).toEqual({ amount: '100000' })
})
