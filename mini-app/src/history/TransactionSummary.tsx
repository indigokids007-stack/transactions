import type { ApiDimension, ApiTransaction } from '../api/types'
import { strings } from '../strings'

type TransactionSummaryProps = {
  transaction: ApiTransaction
  dimensions: ApiDimension[]
}

function dimensionName(dimensions: ApiDimension[], dimensionId: number): string | null {
  return dimensions.find((dimension) => dimension.id === dimensionId)?.name ?? null
}

// The read-only recap shown instead of `TransactionFields` when `canManageTransaction`
// says the signed-in user may not change this row — a manager looking at a colleague's
// entry, or an owner looking at anyone's. Plain text, not disabled form controls: a
// disabled `<select>` still looks like a control waiting for permission, where this is
// the same "view only" fact restated in the field labels the editable form itself uses.
export function TransactionSummary({ transaction, dimensions }: TransactionSummaryProps) {
  return (
    <dl className="space-y-1 text-sm">
      <div className="flex justify-between gap-2">
        <dt style={{ color: 'var(--tg-hint)' }}>{strings.entry.type}</dt>
        <dd>{strings.entry[transaction.type]}</dd>
      </div>
      <div className="flex justify-between gap-2">
        <dt style={{ color: 'var(--tg-hint)' }}>{strings.entry.category}</dt>
        <dd>{transaction.category.name}</dd>
      </div>
      <div className="flex justify-between gap-2">
        <dt style={{ color: 'var(--tg-hint)' }}>{strings.entry.date}</dt>
        <dd>{transaction.occurred_on}</dd>
      </div>
      {transaction.note ? (
        <div className="flex justify-between gap-2">
          <dt style={{ color: 'var(--tg-hint)' }}>{strings.entry.note}</dt>
          <dd>{transaction.note}</dd>
        </div>
      ) : null}
      {transaction.dimension_values.map((value) => (
        <div key={value.dimension_id} className="flex justify-between gap-2">
          <dt style={{ color: 'var(--tg-hint)' }}>
            {dimensionName(dimensions, value.dimension_id) ?? value.dimension_key}
          </dt>
          <dd>{value.value_name}</dd>
        </div>
      ))}
    </dl>
  )
}
