import { MoneyAmount } from '../ui/Money'
import type { GroupRow } from './CurrencySection'

export type StaffSectionProps = {
  currency: string
  /** Already ranked highest spend first — this component renders order, it doesn't decide it. */
  rows: GroupRow[]
}

// One currency's ranking of people by spend, highest first. Never receives another
// currency's rows, and never combines with one — the caller (`StaffView`) is the only
// place currencies are split apart and ranked, and this component has no way to reorder
// or add two of them back together. Mirrors `CurrencySection` and `TrendSection`: a
// dumb renderer of whatever `rows` it's handed.
export function StaffSection({ currency, rows }: StaffSectionProps) {
  return (
    <section
      data-testid={`staff-${currency}`}
      className="rounded-xl border p-4 shadow-sm"
      style={{ borderColor: 'var(--border)', background: 'var(--surface-card)' }}
    >
      <h3 className="text-sm font-semibold">{currency}</h3>

      <ol className="mt-2 flex flex-col gap-1 text-sm">
        {rows.map((row) => (
          <li key={row.key ?? row.label} data-testid="staff-row" className="flex justify-between gap-2">
            <span>{row.label}</span>
            {/* See `CurrencySection`'s equivalent comment: `row.amount` is the exact
                decimal string, `row.amount_minor` is the same figure as a lossy JSON number
                once summed above 2^53. */}
            <MoneyAmount amount={row.amount} currency={row.currency} />
          </li>
        ))}
      </ol>
    </section>
  )
}
