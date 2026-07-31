import { render, screen, getDefaultNormalizer } from "@testing-library/react"
import { Money, formatMoney } from "./Money"

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
