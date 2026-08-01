import { render, screen, getDefaultNormalizer } from "@testing-library/react"
import { Money, MoneyAmount, formatMoney, formatMoneyString, toChartThousands } from "./Money"

const exponents = { UZS: 0, USD: 2 }

describe("formatMoney", () => {
  it("formats a zero-exponent currency with no decimals", () => {
    expect(formatMoney(120000, "UZS", exponents)).toBe("120 000")
  })

  it("formats a two-decimal currency", () => {
    expect(formatMoney(1234, "USD", exponents)).toBe("12.34")
  })

  it("groups thousands with a non-breaking space", () => {
    expect(formatMoney(1500000, "UZS", exponents)).toBe("1 500 000")
  })

  it("falls back to zero decimals for an unknown currency", () => {
    expect(formatMoney(500, "XXX", exponents)).toBe("500")
  })
})

describe("Money", () => {
  it("renders the amount and its currency", () => {
    render(<Money minor={120000} currency="UZS" exponents={exponents} />)
    // Testing Library's default text normalizer collapses all whitespace, including
    // U+00A0, to a plain space, so a nbsp-containing pattern never matches the
    // normalized text unless whitespace collapsing is turned off for this query.
    expect(
      screen.getByText(/120 000/, {
        normalizer: getDefaultNormalizer({ collapseWhitespace: false }),
      }),
    ).toBeInTheDocument()
    expect(screen.getByText(/UZS/)).toBeInTheDocument()
  })
})

describe("formatMoneyString", () => {
  // The exact scenario the review named: ten maximal transactions (1e15 minor units each)
  // plus one more minor unit sums to 10000000000000001 — one past Number.MAX_SAFE_INTEGER
  // (2^53 - 1 = 9007199254740991). A JS number (and so `JSON.parse`) rounds this down to
  // 10000000000000000; the string form must not.
  it("groups a whole number above Number.MAX_SAFE_INTEGER without losing a digit", () => {
    expect(formatMoneyString("10000000000000001")).toBe("10 000 000 000 000 001")
  })

  // Round 2's finding: the case above only ever exercised a zero-exponent currency (UZS),
  // whose amount string is a bare integer with no fraction to lose — `Money::toDecimal`
  // never divides at all on that path, so it passed whether or not the backend's fraction
  // arithmetic was exact. `10000000000000001` minor units in a *two*-decimal currency (USD)
  // is what actually exercises the bug the review found: the backend used to build this
  // string via `$minor / (10 ** $exponent)`, a float division that rounds once `$minor`
  // passes 2^53, rendering "100000000000000.00" and silently dropping the last cent. This
  // string is not typed by hand to look plausible — it is
  // `App\Support\Money::toDecimal(10000000000000001, 'USD')`'s real output, confirmed by
  // running it live via `docker compose exec app php artisan tinker` against the fixed
  // implementation (the matching case now also lives in `tests/Unit/MoneyTest.php`), so
  // this fails again the same way if the backend's arithmetic ever regresses to a float.
  it("keeps the fraction the backend actually produces for an amount above the float-safe range", () => {
    expect(formatMoneyString("100000000000000.01")).toBe("100 000 000 000 000.01")
  })

  it("keeps the fraction and the sign", () => {
    expect(formatMoneyString("-1234567.89")).toBe("-1 234 567.89")
  })

  it("leaves a short whole number with no fraction untouched", () => {
    expect(formatMoneyString("500")).toBe("500")
  })
})

describe("MoneyAmount", () => {
  it("renders a report aggregate's string amount exactly, digit for digit", () => {
    render(<MoneyAmount amount="10000000000000001" currency="UZS" />)

    expect(
      screen.getByText(/10 000 000 000 000 001/, {
        normalizer: getDefaultNormalizer({ collapseWhitespace: false }),
      }),
    ).toBeInTheDocument()
    // The value a JS number would have rounded this down to, proving this path never
    // routed the figure through one.
    expect(screen.queryByText(/10 000 000 000 000 000\b/)).not.toBeInTheDocument()
    expect(screen.getByText(/UZS/)).toBeInTheDocument()
  })

  // The two-decimal counterpart of the case above: the real string a USD report aggregate
  // would carry once the backend stops rounding through a float (see the comment on the
  // matching `formatMoneyString` case for how this value was obtained).
  it("renders a two-decimal report aggregate exactly, including the last cent", () => {
    render(<MoneyAmount amount="100000000000000.01" currency="USD" />)

    expect(
      screen.getByText(/100 000 000 000 000\.01/, {
        normalizer: getDefaultNormalizer({ collapseWhitespace: false }),
      }),
    ).toBeInTheDocument()
    expect(screen.queryByText(/100 000 000 000 000\.00/)).not.toBeInTheDocument()
    expect(screen.getByText(/USD/)).toBeInTheDocument()
  })
})

describe("toChartThousands", () => {
  it("divides an exact multiple of 1000", () => {
    expect(toChartThousands("1000")).toBe("1")
    expect(toChartThousands("1000000")).toBe("1000")
  })

  it("rounds half up rather than truncating", () => {
    expect(toChartThousands("1234500")).toBe("1235") // remainder 500 -> rounds up
    expect(toChartThousands("1234499")).toBe("1234") // remainder 499 -> rounds down
  })

  it("keeps the sign, and drops it if the rounded result is exactly zero", () => {
    expect(toChartThousands("-2000")).toBe("-2")
    expect(toChartThousands("-400")).toBe("0")
  })

  it("never routes through a JS number: exact at magnitudes where Number would already be lossy", () => {
    // 17 digits, past Number.MAX_SAFE_INTEGER (2^53 ~= 9.007e15) — BigInt division stays
    // exact where `Number(amount) / 1000` would already have silently rounded the input.
    expect(toChartThousands("12345678901234567")).toBe("12345678901235")
  })

  it("is defensive against a stray decimal point, dropping it rather than throwing", () => {
    // UZS never carries one; this only matters if a caller applies it to the wrong
    // currency's amount, which must degrade rather than crash the chart.
    expect(toChartThousands("42.00")).toBe("0")
  })
})
