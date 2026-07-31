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
