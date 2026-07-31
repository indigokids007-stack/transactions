/**
 * A port of the backend's `App\Services\Telegram\AmountNoteParser`, minus the note
 * splitting: the mini app has a dedicated amount field, so the whole field is the amount
 * and a leftover remainder refuses instead of becoming a note.
 *
 * A dot or a comma is a thousands separator, never a decimal point. A space groups
 * nothing. A magnitude suffix multiplies and may carry one fractional part, so `1.5k`
 * and `1.5 ming` are both 1500. Every rule errs toward refusal: the server re-validates,
 * so a refusal costs one retype while a divergence costs a 422 at best.
 */

/** Digits one separator stands for, and so the width a short last group is padded to. */
const GROUP_SIZE = 3

const zeros = (count: number, ...words: string[]): Record<string, number> =>
  Object.fromEntries(words.map((word) => [word, count]))

/**
 * What multiplies an amount, mapped to the zeros it appends, mirroring the backend's
 * MAGNITUDES entry for entry. Every missing spelling is a live thousandfold error, so the
 * table is generous on purpose.
 */
const MAGNITUDES: Readonly<Record<string, number>> = {
  ...zeros(3, 'k', 'к', 'ming', 'mingta', 'минг', 'мингта', 'тыс', 'тысяч', 'тысяча', 'тыщ', 'тыща', 'тыщи'),
  ...zeros(6, 'mln', 'млн', 'million', 'millon', 'миллион', 'миллон', 'мильон', 'лям'),
  ...zeros(9, 'mlrd', 'млрд', 'milliard', 'миллиард'),
}

/** The ledger bound, applied to the fully built digit string. Mirrors Money::AMOUNT_PATTERN, whose optional fraction is unreachable here because the string is built from digit runs only. */
const BOUND = /^[0-9]{1,15}$/

/**
 * The exact set the backend flattens, which is neither engine's default. PHP compiles `/u`
 * with PCRE2_UCP, so its `[\s\p{Z}]` is Unicode-aware and covers 26 code points; the extra
 * two over `\p{White_Space}` are U+0085, which PCRE's `\v` adds, and U+180E, which its `\h`
 * adds. JavaScript's own `\s` is a different set again: it omits both and swallows U+FEFF,
 * which PCRE does not, so borrowing it would let the client read a byte order mark the
 * server refuses. A code-point sweep of both engines confirms this class and PHP's agree on
 * all 1,114,112 code points, in both directions.
 *
 * Bank apps and spreadsheets paste U+00A0, U+202F, U+2009 and U+2007 as thousands
 * separators, and those must refuse exactly the way `100 000` refuses.
 */
const WHITESPACE = /[\p{White_Space}\u180E]/gu

/** What PHP's `trim` strips once every whitespace character is already a space. */
const TRIMMABLE = /^[ \0]+|[ \0]+$/g

const utf8Length = (word: string): number => new TextEncoder().encode(word).length

/**
 * The one-character entries, each guarded by a lookahead, because `k` would otherwise
 * match inside `kishi`, `kg` and `kun`, and its Cyrillic twin `к` inside `кг` and
 * `картошка`. Length is counted in characters, not bytes: `к` is one character and two
 * bytes, and a byte count would hand every non-ASCII one-letter magnitude an unguarded
 * fragment.
 */
function letterAlternation(): string {
  return Object.keys(MAGNITUDES)
    .filter((word) => [...word].length === 1)
    .map((letter) => `${letter}(?![\\p{L}0-9])`)
    .join('|')
}

/**
 * The entries of two characters or more, longest first so the alternation reads the way
 * it matches rather than leaning on backtracking. A whole word needs no lookahead,
 * because the token rule already demands the end of the field after a magnitude.
 */
function wordAlternation(): string {
  return Object.keys(MAGNITUDES)
    .filter((word) => [...word].length > 1)
    .sort((word, other) => utf8Length(other) - utf8Length(word))
    .join('|')
}

/**
 * A run of digits and separators, optionally multiplied by a magnitude, and only a space
 * or the end of the field may follow, so `120000taksi` and `2mlnsom` refuse rather than
 * split. Glued, any magnitude reads; a space apart, a whole word reads; a space apart, a
 * one-letter magnitude reads only at the end, because `к` is an ordinary Russian
 * preposition. The remainder is captured the way the backend captures it so the pattern
 * stays comparable, and then refused: this field holds no note.
 */
function tokenPattern(): RegExp {
  const letters = letterAlternation()

  return new RegExp(
    `^(?<token>[0-9.,]+)(?<magnitude>(?:${letters})` +
      `| *(?:${wordAlternation()})` +
      `| +(?:${letters})(?=$))?(?<remainder> .*|)$`,
    'iu',
  )
}

const TOKEN = tokenPattern()

const isDigitRun = (value: string): boolean => /^[0-9]+$/.test(value)

/** One group of a separated amount: one to three digits. */
const isGroup = (value: string): boolean => value.length <= GROUP_SIZE && isDigitRun(value)

/**
 * `100k`, `1.5k`, `30 ming`, `1.5 mln`. The magnitude carries the multiplication, so at
 * most one separator may follow the whole part, holding a fraction of the magnitude that
 * is padded out to its zeros. The digit string is built by concatenation, never by float
 * arithmetic, so `999999999999 mln` refuses for its width instead of overflowing.
 */
function amountFromMagnitude(groups: string[], zeros: number): string | null {
  if (groups.length > 2) return null

  const [whole, fraction = ''] = groups

  if (!isDigitRun(whole)) return null
  if (fraction !== '' && (!isDigitRun(fraction) || fraction.length > zeros)) return null

  return whole + fraction.padEnd(zeros, '0')
}

/**
 * `100000`, `100.000`, `12.50`, `1.250.000`. With exactly one separator a short final
 * group is shorthand and is padded out, so `12.50` is 12500. With two or more the number
 * is already written in groups and every group after the first must be a full three
 * digits, otherwise padding would turn the European `1.000,00` into a million.
 */
function amountFromGroups(groups: string[]): string | null {
  const [leading, ...trailing] = groups

  if (trailing.length === 0) return isDigitRun(leading) ? leading : null
  if (!isGroup(leading)) return null

  if (trailing.length === 1) {
    return isGroup(trailing[0]) ? leading + trailing[0].padEnd(GROUP_SIZE, '0') : null
  }

  return trailing.every((group) => group.length === GROUP_SIZE && isDigitRun(group))
    ? leading + trailing.join('')
    : null
}

function boundedAmount(digits: string): string | null {
  const amount = digits.replace(/^0+/, '')

  return amount !== '' && BOUND.test(amount) ? amount : null
}

export function parseAmount(input: string): { amount: string } | null {
  const field = input.replace(WHITESPACE, ' ').replace(TRIMMABLE, '')
  const groups = TOKEN.exec(field)?.groups

  if (groups === undefined || groups.remainder !== '') return null

  const zeros = MAGNITUDES[(groups.magnitude ?? '').trim().toLowerCase()] ?? 0
  const parts = groups.token.replace(/,/g, '.').split('.')
  const digits = zeros === 0 ? amountFromGroups(parts) : amountFromMagnitude(parts, zeros)
  const amount = digits === null ? null : boundedAmount(digits)

  return amount === null ? null : { amount }
}
