import { useEffect } from 'react'
import type { ApiClient } from '../api/client'
import type { Bootstrap } from '../api/types'
import { strings } from '../strings'
import { webApp } from '../telegram/webApp'
import { Toast } from '../ui/Toast'
import { AmountKeypad } from './AmountKeypad'
import { CategoryChips } from './CategoryChips'
import { DetailsSheet } from './DetailsSheet'
import { useEntryForm } from './useEntryForm'

export type EntryScreenProps = {
  bootstrap: Bootstrap
  client: ApiClient
}

// The staff expense entry screen: an amount keypad, the category chips, a collapsible
// details sheet, and a save action. Telegram's MainButton mirrors `canSave` and drives
// `save()` when the app is embedded; outside Telegram (a browser tab, or a test) an
// ordinary button takes its place, since there is no MainButton to click there.
export function EntryScreen({ bootstrap, client }: EntryScreenProps) {
  const form = useEntryForm(bootstrap, client)
  const insideTelegram = window.Telegram?.WebApp !== undefined

  // MainButton is Telegram's own chrome, not something this tree renders, so binding and
  // unbinding its click handler is a genuine effect: it synchronises with an external
  // system rather than deriving anything from render.
  useEffect(() => {
    const button = webApp().MainButton
    button.setText(strings.entry.save)
    button.show()

    function handleClick(): void {
      void form.save()
    }

    button.onClick(handleClick)
    return () => {
      button.offClick(handleClick)
    }
  }, [form.save])

  useEffect(() => {
    const button = webApp().MainButton
    if (form.canSave) {
      button.enable()
    } else {
      button.disable()
    }
  }, [form.canSave])

  return (
    <div className="flex flex-col pb-6">
      <div className="p-4 text-center text-4xl font-semibold tabular-nums">
        {form.values.amountInput || '0'}
        <span className="ml-2 text-lg opacity-70">{form.values.currency}</span>
      </div>

      <CategoryChips
        categories={form.categories}
        type={form.values.type}
        selectedId={form.values.categoryId}
        defaultId={bootstrap.defaults.category_id}
        onSelect={form.setCategory}
      />

      <AmountKeypad value={form.values.amountInput} onChange={form.setAmount} />

      <DetailsSheet
        values={form.values}
        dimensions={form.dimensions}
        currencies={form.currencies}
        missingRequired={form.missingRequired}
        onTypeChange={form.setType}
        onCurrencyChange={form.setCurrency}
        onDateChange={form.setDate}
        onNoteChange={form.setNote}
        onDimensionChange={form.setDimension}
      />

      {form.notice ? (
        <p role="alert" className="px-4 py-2 text-sm" style={{ color: 'var(--tg-hint)' }}>
          {form.notice}
        </p>
      ) : null}

      {!insideTelegram ? (
        <button
          type="button"
          disabled={!form.canSave}
          onClick={() => void form.save()}
          className="mx-4 mt-2 rounded-full py-3 text-center font-semibold disabled:opacity-50"
          style={{ background: 'var(--tg-button)', color: 'var(--tg-button-text)' }}
        >
          {strings.entry.save}
        </button>
      ) : null}

      {form.lastSaved ? (
        <Toast message={strings.entry.saved} action={strings.entry.undo} onAction={() => void form.undo()} />
      ) : null}
    </div>
  )
}
