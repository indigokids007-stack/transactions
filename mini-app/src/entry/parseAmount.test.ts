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

// The 25 code points Unicode gives the White_Space property, written out rather than read
// back from `\p{White_Space}`, so the assertion below is a fixed contract and not two
// implementations agreeing with each other. Derived by sweeping every code point through
// `preg_match('/\p{White_Space}/u', ...)` in the container, not transcribed from docs.
const WHITE_SPACE = [
  0x0009, 0x000a, 0x000b, 0x000c, 0x000d, 0x0020, 0x0085, 0x00a0, 0x1680,
  0x2000, 0x2001, 0x2002, 0x2003, 0x2004, 0x2005, 0x2006, 0x2007, 0x2008, 0x2009, 0x200a,
  0x2028, 0x2029, 0x202f, 0x205f, 0x3000,
]

// Unicode 6.3 moved U+180E out of White_Space, but PCRE still reaches it through `\h`, so
// PHP's `[\s\p{Z}]` flattens it and so must this parser. It is the only addition: the same
// sweep returns 26 for `[\s\p{Z}]` and these 25 for the property, differing here alone.
const FLATTENED = [...WHITE_SPACE, 0x180e].sort((a, b) => a - b)

const named = FLATTENED.map((cp): [string, string] => [
  cp.toString(16).toUpperCase().padStart(4, '0'),
  String.fromCodePoint(cp),
])

it.each(named)('flattens U+%s to a space that groups nothing', (_name, space) => {
  expect(parseAmount(`100${space}000`)).toBeNull()
  expect(parseAmount(`100000${space}k`)).toEqual({ amount: '100000000' })
  expect(parseAmount(`${space}7${space}ming${space}`)).toEqual({ amount: '7000' })
})

// Whoever next edits the WHITESPACE comment in parseAmount.ts: this is the test that pins
// it. Prose about Unicode set membership is invisible to every other test in this file, and
// that comment has already been wrong twice while the code it describes was right. Re-run
// the two-engine sweep before changing either, and expect this test to fail if the class
// gains a code point or loses one.
//
// A flattened character makes `7<c>ming` read 7000 the way `7 ming` does, and leaves
// `100<c>000` refusing the way `100 000` does. Both halves are needed: `7.ming` is 7000 too,
// because the token swallows a separator, so the first probe alone reports `.` and `,` as
// whitespace.
it('flattens exactly the 26 code points PHP flattens, and no others', () => {
  const flattens = (cp: number): boolean => {
    const c = String.fromCodePoint(cp)
    return parseAmount(`7${c}ming`)?.amount === '7000' && parseAmount(`100${c}000`) === null
  }

  const actual: number[] = []
  for (let cp = 0; cp <= 0x10ffff; cp++) {
    if (cp >= 0xd800 && cp <= 0xdfff) continue
    if (flattens(cp)) actual.push(cp)
  }

  expect(actual).toEqual(FLATTENED)
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

