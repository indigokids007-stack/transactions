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

// The 15-digit bound, applied to the digit string after the magnitude has been built into
// it, so a magnitude cannot overflow past it.
it('reads a magnitude up to the bound and no further', () => {
  expect(parseAmount('999999999999.999k')).toEqual({ amount: '999999999999999' })
  expect(parseAmount('999999999999999k')).toBeNull()
  expect(parseAmount('999999999999999')).toEqual({ amount: '999999999999999' })
  expect(parseAmount('1000000000000000')).toBeNull()
})

// Leading zeros are stripped, and a short final group is padded out to three digits.
const padded: [string, string][] = [
  ['0.500', '500'],
  ['007', '7'],
  ['1.5', '1500'],
]

it.each(padded)('reads %s as %s', (input, expected) => {
  expect(parseAmount(input)).toEqual({ amount: expected })
})

// One row per rule that, if loosened, would make the client read a number the server
// refuses. Each was checked against a mutant with that one rule removed and fails there.
// Without these rows the suite passes every one of those mutants.
const discriminating: [string, string][] = [
  ['the leading group is capped at three digits', '1234.567'],
  ['a trailing group is capped at three digits', '100.0000'],
  ['a magnitude takes at most one separator', '1.5.5k'],
  ['a fraction is capped at the magnitude zeros', '1.9999k'],
  ['U+FEFF is whitespace to JavaScript but not to PCRE', '100000\uFEFF'],
  ['U+200B is whitespace to neither', '100\u200B000'],
]

it.each(discriminating)('refuses because %s: %j', (_rule, input) => {
  expect(parseAmount(input)).toBeNull()
})

// PHP compiles `/u` with PCRE2_UCP, so its `[\s\p{Z}]` covers U+0085 and U+180E on top of
// `\p{White_Space}`. Every one must flatten to a space here exactly as it does there:
// grouping nothing on its own, and separating a magnitude the way an ASCII space does.
const flattened = [
  '\u0085',
  '\u180E',
  '\u00A0',
  '\u1680',
  '\u2007',
  '\u2009',
  '\u2028',
  '\u202F',
  '\u205F',
  '\u3000',
]

it.each(flattened)('flattens %j to a space that groups nothing', (space) => {
  expect(parseAmount(`100${space}000`)).toBeNull()
  expect(parseAmount(`100000${space}k`)).toEqual({ amount: '100000000' })
  expect(parseAmount(`${space}7${space}ming${space}`)).toEqual({ amount: '7000' })
})

// A note in the amount field is not an amount: this field has no note half to split off.
const refusedFurther = [
  '1 000 000',
  '100 00',
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

