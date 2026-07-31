import { render, screen, getDefaultNormalizer } from "@testing-library/react"
import { Money, MoneyAmount, formatMoney, formatMoneyString } from "./Money"

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
})
