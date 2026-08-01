import type { ApiTransaction } from '../api/types'
import { strings } from '../strings'
import { Money } from '../ui/Money'
import type { RevisionCountState } from './useRevisionCount'

type TransactionHeaderProps = {
  transaction: ApiTransaction
  exponents: Record<string, number>
  revisionState: RevisionCountState
}

// The full-record facts no edit form ever shows: the amount (never editable — see
// `transactionEdit.ts`'s doc comment), whose record it is, which department it belongs
// to, and how many times it has been revised. Shown regardless of
// `canManageTransaction`'s answer, since "amount editing is out of scope" never meant
// "hide the amount", only "don't offer to change it" — the same reasoning that keeps the
// currency visible here after Fix 2 removed it as an editable control.
export function TransactionHeader({ transaction, exponents, revisionState }: TransactionHeaderProps) {
  const income = transaction.type === 'income'
  return (
    <div>
      <div className="flex items-start justify-between gap-3">
        <div>
          <div
            style={{
              font: '800 26px/1 "Plus Jakarta Sans"',
              color: income ? 'var(--income)' : 'var(--expense)',
              fontVariantNumeric: 'tabular-nums',
            }}
          >
            {income ? '+' : String.fromCharCode(0x2212)}
            <Money minor={transaction.amount_minor} currency={transaction.currency} exponents={exponents} />
          </div>
          <div className="mt-1.5" style={{ font: '500 12px/1 "Plus Jakarta Sans"', color: 'var(--muted)' }}>
            {strings.entry[transaction.type]} · {transaction.department?.name ?? transaction.user.name}
          </div>
        </div>
        <span
          data-testid="revision-count"
          style={{ font: '600 11px/1 "Plus Jakarta Sans"', color: 'var(--ink-2)', background: 'var(--pill-bg)', padding: '8px 11px', borderRadius: 999 }}
        >
          {`${strings.history.revisions}: `}
          {revisionState.status === 'loading' ? strings.history.revisionsLoading : null}
          {revisionState.status === 'loaded' ? revisionState.count : null}
          {revisionState.status === 'failed' ? (
            <span className="inline-flex items-center gap-2">
              <span>{strings.history.revisionsFailed}</span>
              <button type="button" onClick={revisionState.retry} className="underline">
                {strings.common.retry}
              </button>
            </span>
          ) : null}
        </span>
      </div>

      <dl className="mt-3.5 space-y-1.5" style={{ font: '600 12px/1 "Plus Jakarta Sans"' }}>
        <div className="flex justify-between gap-2">
          <dt style={{ color: 'var(--muted)' }}>{strings.history.person}</dt>
          <dd style={{ color: 'var(--ink-2)' }}>{transaction.user.name}</dd>
        </div>
        <div className="flex justify-between gap-2">
          <dt style={{ color: 'var(--muted)' }}>{strings.history.department}</dt>
          <dd style={{ color: 'var(--ink-2)' }}>{transaction.department?.name ?? strings.history.noDepartment}</dd>
        </div>
      </dl>
    </div>
  )
}
