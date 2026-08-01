import { MoneyAmount } from '../ui/Money'
import { CHART_COLORS } from './CurrencySection'
import type { GroupRow } from './CurrencySection'

export type StaffSectionProps = {
  currency: string
  /** Already ranked highest spend first — this component renders order, it doesn't decide it. */
  rows: GroupRow[]
}

function initials(name: string): string {
  return name
    .split(' ')
    .map((word) => word[0])
    .join('')
}

// One currency's ranking of people by spend, highest first. Never receives another
// currency's rows, and never combines with one — the caller (`StaffView`) is the only
// place currencies are split apart and ranked, and this component has no way to reorder
// or add two of them back together. Mirrors `CurrencySection` and `TrendSection`: a
// dumb renderer of whatever `rows` it's handed.
export function StaffSection({ currency, rows }: StaffSectionProps) {
  const maxAmount = Math.max(...rows.map((row) => row.amount_minor), 1)

  return (
    <section
      data-testid={`staff-${currency}`}
      style={{ background: 'var(--surface)', borderRadius: 'var(--r-card)', padding: 20, boxShadow: 'var(--shadow-card)' }}
    >
      <h3 style={{ font: '700 13px/1 "Plus Jakarta Sans"', color: 'var(--ink)', marginBottom: 6 }}>{currency}</h3>

      <ol className="flex flex-col">
        {rows.map((row, index) => {
          const color = CHART_COLORS[index % CHART_COLORS.length]
          const width = Math.round((row.amount_minor / maxAmount) * 100)
          return (
            <li
              key={row.key ?? row.label}
              data-testid="staff-row"
              style={{ padding: '14px 0', borderBottom: '1px solid var(--line-2)' }}
            >
              <div className="flex items-center justify-between" style={{ marginBottom: 8 }}>
                <span className="flex items-center gap-2.5">
                  <span
                    aria-hidden="true"
                    className="flex items-center justify-center"
                    style={{
                      width: 30,
                      height: 30,
                      borderRadius: 11,
                      font: '700 10px/1 "Plus Jakarta Sans"',
                      color: '#fff',
                      background: color,
                    }}
                  >
                    {initials(row.label)}
                  </span>
                  <span style={{ font: '600 13px/1 "Plus Jakarta Sans"', color: 'var(--ink)' }}>{row.label}</span>
                </span>
                {/* See `CurrencySection`'s equivalent comment: `row.amount` is the exact
                    decimal string, `row.amount_minor` is the same figure as a lossy JSON number
                    once summed above 2^53. */}
                <span style={{ font: '700 13px/1 "Plus Jakarta Sans"', color: 'var(--ink-2)', fontVariantNumeric: 'tabular-nums' }}>
                  <MoneyAmount amount={row.amount} currency={row.currency} />
                </span>
              </div>
              <div style={{ height: 8, borderRadius: 999, background: 'var(--pill-bg)', overflow: 'hidden' }}>
                <div style={{ height: '100%', borderRadius: 999, background: color, width: `${width}%` }} />
              </div>
            </li>
          )
        })}
      </ol>
    </section>
  )
}
