import { MoneyAmount } from '../ui/Money'
import { strings } from '../strings'
import type { AggregateRow } from '../api/types'

export type GroupRow = AggregateRow & { key: string | null; label: string }

export type CurrencySectionProps = {
  currency: string
  totals: AggregateRow[]
  groups: GroupRow[]
}

// The dataviz skill's validated 8-slot categorical order (references/palette.md),
// fixed — never cycled, never generated. Referenced as CSS custom properties (defined
// in styles.css) rather than raw hex, so the palette swaps in one place.
export const CHART_COLORS = [
  'var(--chart-1)',
  'var(--chart-2)',
  'var(--chart-3)',
  'var(--chart-4)',
  'var(--chart-5)',
  'var(--chart-6)',
  'var(--chart-7)',
  'var(--chart-8)',
]

const MAX_CHART_SLOTS = CHART_COLORS.length
const DONUT_RADIUS = 48
const DONUT_CIRCUMFERENCE = 2 * Math.PI * DONUT_RADIUS

// Caps a currency's groups at the validated 8-slot color order: the first 7 pass
// through untouched, everything from the 8th group on folds into one synthetic "Other"
// slice. `amount_minor` and `count` sum in plain number arithmetic — the same
// precision the pie already accepted for its slice sizing before this change, since
// nothing here displays the folded slice's currency amount as text (only its label and
// its share of the donut). Never mutates `groups`.
export function foldGroupsToSlots(groups: GroupRow[], otherLabel: string): GroupRow[] {
  if (groups.length <= MAX_CHART_SLOTS) return groups

  const kept = groups.slice(0, MAX_CHART_SLOTS - 1)
  const folded = groups.slice(MAX_CHART_SLOTS - 1)

  const other: GroupRow = {
    key: 'other',
    label: otherLabel,
    currency: folded[0].currency,
    type: folded[0].type,
    amount_minor: folded.reduce((sum, row) => sum + row.amount_minor, 0),
    amount: String(folded.reduce((sum, row) => sum + row.amount_minor, 0)),
    count: folded.reduce((sum, row) => sum + row.count, 0),
  }

  return [...kept, other]
}

type DonutSegment = {
  key: string
  color: string
  label: string
  pct: string
  dasharray: string
  dashoffset: string
}

// Lays out each slot's stroke-dasharray/offset around the ring, in order, leaving a 2px
// gap between segments — pure geometry over already-folded slots, no state.
function donutSegments(slots: GroupRow[]): DonutSegment[] {
  const total = slots.reduce((sum, slot) => sum + slot.amount_minor, 0)
  let consumed = 0

  return slots.map((slot, index) => {
    const fraction = total === 0 ? 0 : slot.amount_minor / total
    const segment: DonutSegment = {
      key: slot.key ?? slot.label,
      color: CHART_COLORS[index % CHART_COLORS.length],
      label: slot.label,
      pct: `${Math.round(fraction * 100)}%`,
      dasharray: `${(DONUT_CIRCUMFERENCE * fraction - 2).toFixed(1)} ${DONUT_CIRCUMFERENCE.toFixed(1)}`,
      dashoffset: `${(-DONUT_CIRCUMFERENCE * consumed).toFixed(1)}`,
    }
    consumed += fraction
    return segment
  })
}

// One currency's slice of a summary report: its own totals, its own donut. Never
// receives another currency's rows, and never combines with one — the caller
// (`SummaryView`) is the only place currencies are split apart, and this component has
// no way to add two of them back together.
export function CurrencySection({ currency, totals, groups }: CurrencySectionProps) {
  const slots = foldGroupsToSlots(groups, strings.reports.other)
  const segments = donutSegments(slots)
  const donutTotal = slots.reduce((sum, slot) => sum + slot.amount_minor, 0)

  return (
    <section
      data-testid={`currency-${currency}`}
      style={{ background: 'var(--surface)', borderRadius: 'var(--r-card)', padding: 20, boxShadow: 'var(--shadow-card)' }}
    >
      <div className="flex items-center justify-between">
        <span style={{ font: '700 13px/1 "Plus Jakarta Sans"', color: 'var(--ink)' }}>{currency}</span>
      </div>

      <dl className="mt-3.5 flex gap-5">
        {totals.map((row) => (
          <div key={row.type}>
            <dt style={{ font: '500 11px/1 "Plus Jakarta Sans"', color: 'var(--muted)' }}>{strings.entry[row.type]}</dt>
            <dd
              className="mt-1"
              style={{
                font: '700 16px/1.2 "Plus Jakarta Sans"',
                color: row.type === 'income' ? 'var(--income)' : 'var(--expense)',
                fontVariantNumeric: 'tabular-nums',
              }}
            >
              {/* `row.amount` is the backend's precision-safe decimal string; `row.amount_minor`
                  is the same figure as a JSON number, which loses precision above 2^53 once
                  enough transactions are summed into one aggregate row. See `MoneyAmount`. */}
              <MoneyAmount amount={row.amount} currency={row.currency} />
            </dd>
          </div>
        ))}
      </dl>

      {segments.length > 0 ? (
        <div className="flex items-center gap-4" style={{ marginTop: 16 }}>
          <svg width={126} height={126} viewBox="0 0 126 126" className="flex-none">
            <circle cx={63} cy={63} r={DONUT_RADIUS} fill="none" stroke="var(--pill-bg)" strokeWidth={20} />
            {segments.map((segment) => (
              <circle
                key={segment.key}
                cx={63}
                cy={63}
                r={DONUT_RADIUS}
                fill="none"
                stroke={segment.color}
                strokeWidth={20}
                strokeDasharray={segment.dasharray}
                strokeDashoffset={segment.dashoffset}
                transform="rotate(-90 63 63)"
                strokeLinecap="butt"
              />
            ))}
            <text x={63} y={59} textAnchor="middle" style={{ font: '700 11px "Plus Jakarta Sans"', fill: 'var(--muted)' }}>
              {strings.reports.total}
            </text>
            <text x={63} y={76} textAnchor="middle" style={{ font: '800 14px "Plus Jakarta Sans"', fill: 'var(--ink)' }}>
              {Math.round(donutTotal / 1_000_000)} mln
            </text>
          </svg>
          <div className="flex flex-1 flex-col gap-[9px]">
            {segments.map((segment) => (
              <div key={segment.key} className="flex items-center gap-2">
                <span
                  aria-hidden="true"
                  className="flex-none"
                  style={{ width: 10, height: 10, borderRadius: 4, background: segment.color, display: 'inline-block' }}
                />
                <span className="flex-1 truncate" style={{ font: '500 12px/1.2 "Plus Jakarta Sans"', color: 'var(--ink-2)' }}>
                  {segment.label}
                </span>
                <span style={{ font: '700 12px/1 "Plus Jakarta Sans"', color: 'var(--ink)', fontVariantNumeric: 'tabular-nums' }}>
                  {segment.pct}
                </span>
              </div>
            ))}
          </div>
        </div>
      ) : null}
    </section>
  )
}
