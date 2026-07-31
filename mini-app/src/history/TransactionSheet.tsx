import { useState } from 'react'
import type { ApiClient } from '../api/client'
import type { ApiTransaction, Bootstrap } from '../api/types'
import { strings } from '../strings'
import { TransactionFields } from './TransactionFields'
import { diffValues, readMessage, valuesFromTransaction, type TransactionEditValues } from './transactionEdit'

export type TransactionSheetProps = {
  transaction: ApiTransaction
  bootstrap: Bootstrap
  client: Pick<ApiClient, 'updateTransaction' | 'deleteTransaction'>
  onClose: () => void
  onSaved: (updated: ApiTransaction) => void
  onDeleted: (id: number) => void
}

// One transaction's edit form plus its delete flow. `HistoryScreen` mounts this with
// `key={transaction.id}`, so switching which row is open always starts a fresh instance
// from that row's own values, rather than an effect resyncing state to a changed prop —
// `original` below is a plain snapshot taken once from the prop, not a ref or an effect.
export function TransactionSheet({
  transaction,
  bootstrap,
  client,
  onClose,
  onSaved,
  onDeleted,
}: TransactionSheetProps) {
  const [original] = useState<TransactionEditValues>(() => valuesFromTransaction(transaction))
  const [values, setValues] = useState<TransactionEditValues>(original)
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [error, setError] = useState<string | null>(null)

  function patch(next: Partial<TransactionEditValues>): void {
    setValues((current) => ({ ...current, ...next }))
  }

  async function save(): Promise<void> {
    setSaving(true)
    setError(null)

    try {
      const response = await client.updateTransaction(transaction.id, diffValues(original, values))
      onSaved(response.data)
    } catch (caught) {
      setError(readMessage(caught, strings.history.actionFailed))
    } finally {
      setSaving(false)
    }
  }

  async function confirmDelete(): Promise<void> {
    setDeleting(true)
    setError(null)

    try {
      await client.deleteTransaction(transaction.id)
      onDeleted(transaction.id)
    } catch (caught) {
      setError(readMessage(caught, strings.history.actionFailed))
      setConfirmingDelete(false)
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 flex flex-col justify-end"
      style={{ background: 'rgba(0,0,0,0.4)' }}
    >
      <div className="flex flex-col gap-3 rounded-t-2xl p-4" style={{ background: 'var(--tg-bg)', color: 'var(--tg-text)' }}>
        <TransactionFields values={values} bootstrap={bootstrap} onChange={patch} />

        {error ? (
          <p role="alert" className="text-sm" style={{ color: 'var(--tg-hint)' }}>
            {error}
          </p>
        ) : null}

        <div className="mt-2 flex gap-2">
          <button
            type="button"
            onClick={onClose}
            className="flex-1 rounded-full py-3 text-center text-sm"
            style={{ background: 'var(--tg-secondary-bg)', color: 'var(--tg-text)' }}
          >
            {strings.common.cancel}
          </button>
          <button
            type="button"
            disabled={saving}
            onClick={() => void save()}
            className="flex-1 rounded-full py-3 text-center font-semibold disabled:opacity-50"
            style={{ background: 'var(--tg-button)', color: 'var(--tg-button-text)' }}
          >
            {strings.common.save}
          </button>
        </div>

        {confirmingDelete ? (
          <div className="flex items-center gap-2">
            <p className="flex-1 text-sm">{strings.history.deleteConfirm}</p>
            <button
              type="button"
              disabled={deleting}
              onClick={() => void confirmDelete()}
              className="rounded-full px-4 py-2 text-sm font-semibold disabled:opacity-50"
              style={{ background: 'var(--tg-button)', color: 'var(--tg-button-text)' }}
            >
              {strings.common.confirm}
            </button>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => setConfirmingDelete(true)}
            className="w-full rounded-full py-2 text-sm"
            style={{ color: 'var(--tg-hint)' }}
          >
            {strings.common.delete}
          </button>
        )}
      </div>
    </div>
  )
}
