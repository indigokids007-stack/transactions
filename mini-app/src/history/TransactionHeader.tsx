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
  return (
    <dl className="space-y-1 border-b pb-3 text-sm" style={{ borderColor: 'var(--tg-hint)' }}>
      <div className="flex items-center justify-between gap-2">
        <dt style={{ color: 'var(--tg-hint)' }}>{strings.entry.amount}</dt>
        <dd className="text-lg font-semibold">
          <Money minor={transaction.amount_minor} currency={transaction.currency} exponents={exponents} />
        </dd>
      </div>
      <div className="flex justify-between gap-2">
        <dt style={{ color: 'var(--tg-hint)' }}>{strings.history.person}</dt>
        <dd>{transaction.user.name}</dd>
      </div>
      <div className="flex justify-between gap-2">
        <dt style={{ color: 'var(--tg-hint)' }}>{strings.history.department}</dt>
        <dd>{transaction.department?.name ?? strings.history.noDepartment}</dd>
      </div>
      <div className="flex items-center justify-between gap-2">
        <dt style={{ color: 'var(--tg-hint)' }}>{strings.history.revisions}</dt>
        <dd data-testid="revision-count">
          {revisionState.status === 'loading' ? strings.history.revisionsLoading : null}
          {revisionState.status === 'loaded' ? revisionState.count : null}
          {revisionState.status === 'failed' ? (
            <span className="inline-flex items-center gap-2">
              <span style={{ color: 'var(--tg-hint)' }}>{strings.history.revisionsFailed}</span>
              <button
                type="button"
                onClick={revisionState.retry}
                className="underline"
              >
                {strings.common.retry}
              </button>
            </span>
          ) : null}
        </dd>
      </div>
    </dl>
  )
}
