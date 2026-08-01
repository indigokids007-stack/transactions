const GROUP_SEPARATOR = " "

export function formatMoney(
  minorUnits: number,
  currency: string,
  exponents: Record<string, number>,
): string {
  const exponent = exponents[currency] ?? 0
  const isNegative = minorUnits < 0
  const digits = Math.abs(minorUnits).toString().padStart(exponent + 1, "0")
  const whole = exponent === 0 ? digits : digits.slice(0, -exponent)
  const fraction = exponent === 0 ? "" : `.${digits.slice(-exponent)}`
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, GROUP_SEPARATOR)

  return `${isNegative ? "-" : ""}${grouped}${fraction}`
}

type MoneyProps = {
  minor: number
  currency: string
  exponents: Record<string, number>
}

export function Money({ minor, currency, exponents }: MoneyProps) {
  return (
    <span className="tabular-nums">
      {formatMoney(minor, currency, exponents)}{" "}
      <span className="text-xs opacity-70">{currency}</span>
    </span>
  )
}

// Groups the whole-number part of an already-formatted decimal string, without ever
// routing it through a JS number. A report aggregate sums many transactions' amounts
// server-side and hands back both `amount_minor` (a JSON number) and `amount` (this same
// value, exact, as a string) — see `AggregateRow` in `api/types.ts`. Ten maximal
// transactions plus one minor unit already exceeds Number.MAX_SAFE_INTEGER (2^53), so
// `JSON.parse` silently rounds `amount_minor`; `amount` never goes through `JSON.parse`'s
// number path at all, so it stays exact no matter how large the sum. The decimal point
// (if any) already sits in the right place — the backend applied the currency's exponent
// before sending it — so, unlike `formatMoney`, this never needs an exponent lookup.
export function formatMoneyString(amount: string): string {
  const isNegative = amount.startsWith("-")
  const unsigned = isNegative ? amount.slice(1) : amount
  const [whole, fraction] = unsigned.split(".")
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, GROUP_SEPARATOR)

  return `${isNegative ? "-" : ""}${grouped}${fraction === undefined ? "" : `.${fraction}`}`
}

// UZS has no minor unit and ordinary entries run into the hundreds of thousands or
// millions, so a raw chart axis or bar label in so'm is hard to read at a glance. Human
// ruling 2026-08-01: scale UZS chart values to thousands (1 000 000 -> 1000), and only
// UZS — a currency with real decimals is already a small, legible number.
export const CHART_SCALED_CURRENCY = 'UZS'

// Divides an exact decimal-string amount by 1000 and rounds to the nearest whole number,
// entirely in integer arithmetic (`BigInt`), so a chart value never routes through the
// same lossy `amount_minor` JSON number `MoneyAmount` above refuses to read from. A
// fractional part is dropped rather than rounded into the result — UZS, the only currency
// this runs on, never carries one; a stray decimal point from a caller that got the
// currency check wrong would otherwise make `BigInt` throw.
export function toChartThousands(amount: string): string {
  const isNegative = amount.startsWith("-")
  const unsigned = isNegative ? amount.slice(1) : amount
  const whole = BigInt(unsigned.split(".")[0])
  const quotient = whole / 1000n
  const remainder = whole % 1000n
  const rounded = remainder * 2n >= 1000n ? quotient + 1n : quotient
  return `${isNegative && rounded !== 0n ? "-" : ""}${rounded.toString()}`
}

type MoneyAmountProps = {
  amount: string
  currency: string
}

// The report-view counterpart to `Money`: renders an `AggregateRow`'s exact `amount`
// string. `Money`/`formatMoney` stay as they are for a single transaction's `amount_minor`
// — the backend caps one transaction at 1e15, safely inside 2^53 even before any summing
// — but nothing that sums transactions may format `amount_minor` for display.
export function MoneyAmount({ amount, currency }: MoneyAmountProps) {
  return (
    <span className="tabular-nums">
      {formatMoneyString(amount)}{" "}
      <span className="text-xs opacity-70">{currency}</span>
    </span>
  )
}
