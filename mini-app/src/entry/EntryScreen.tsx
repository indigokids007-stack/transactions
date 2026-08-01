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
  /**
   * Whether the Add tab is the one showing. All three tab panels stay mounted (so each
   * keeps its own state across a switch — see `App.tsx`), so without this `EntryScreen`
   * would keep Telegram's MainButton bound and tappable while the user is looking at
   * Reports or History, saving an entry from a screen they can't see. Defaults to `true`
   * so a bare `<EntryScreen>` (as most tests render it) behaves the way it always did.
   */
  active?: boolean
}

// The staff expense entry screen: an amount keypad, the category chips, a collapsible
// details sheet, and a save action. Telegram's MainButton mirrors `canSave` and drives
// `save()` when the app is embedded; outside Telegram (a browser tab, or a test) an
// ordinary button takes its place, since there is no MainButton to click there.
export function EntryScreen({ bootstrap, client, active = true }: EntryScreenProps) {
  const form = useEntryForm(bootstrap, client)
  const insideTelegram = window.Telegram?.WebApp !== undefined

  // MainButton is Telegram's own chrome, not something this tree renders, so binding and
  // unbinding its click handler is a genuine effect: it synchronises with an external
  // system rather than deriving anything from render. While another tab is showing, the
  // handler is never bound AND the button is hidden — gating the handler alone stops a
  // save from an invisible screen, but leaves a live-looking "Saqlash" on screen with a
  // tap that silently does nothing, which is its own confusing failure. `show()`/`setText`
  // re-run and restore it the moment `active` goes back to `true`.
  useEffect(() => {
    const button = webApp().MainButton

    if (!active) {
      button.hide()
      return
    }

    button.setText(strings.entry.save)
    button.show()

    function handleClick(): void {
      void form.save()
    }

    button.onClick(handleClick)
    return () => {
      button.offClick(handleClick)
    }
  }, [form.save, active])

  useEffect(() => {
    const button = webApp().MainButton

    if (!active) {
      button.disable()
      return
    }

    if (form.canSave) {
      button.enable()
    } else {
      button.disable()
    }
  }, [form.canSave, active])

  return (
    <div className="flex flex-col pb-6">
      <div className="flex items-baseline justify-center gap-2 p-4">
        {/* A real text input, not a display span: the approved amount grammar (comma,
            `k`, `ming`, `mln`, `mlrd`, and their Cyrillic spellings — see `parseAmount`'s
            doc comment) is typed here directly. `inputMode="decimal"` only hints a
            numeric-leaning keyboard; the field still accepts the letters that grammar
            needs, exactly the way a plain `type="text"` would. The keypad below stays as
            a convenience for the pure-digit case, writing into the same value. */}
        <input
          type="text"
          inputMode="decimal"
          aria-label={strings.entry.amount}
          value={form.values.amountInput}
          onChange={(event) => form.setAmount(event.target.value)}
          placeholder="0"
          className="w-48 bg-transparent text-right text-4xl font-semibold tabular-nums outline-none"
          style={{ color: 'var(--tg-text)' }}
        />
        <span className="text-lg opacity-70">{form.values.currency}</span>
      </div>

      {form.amountInvalid ? (
        <p role="alert" className="px-4 text-center text-sm" style={{ color: 'var(--tg-hint)' }}>
          {strings.entry.invalidAmount}
        </p>
      ) : null}

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

      {Object.keys(form.fieldErrors).length > 0 ? (
        <ul role="alert" className="space-y-1 px-4 py-2 text-sm" style={{ color: 'var(--tg-hint)' }}>
          {Object.entries(form.fieldErrors).map(([field, messages]) => (
            <li key={field}>{messages[0]}</li>
          ))}
        </ul>
      ) : null}

      {!insideTelegram ? (
        <button
          type="button"
          disabled={!form.canSave}
          onClick={() => void form.save()}
          className="mx-4 mt-2 rounded-full py-3 text-center font-semibold disabled:opacity-50"
          style={{ background: 'var(--accent)', color: 'var(--accent-text)' }}
        >
          {strings.entry.save}
        </button>
      ) : null}

      {form.lastSaved ? (
        <Toast
          message={strings.entry.saved}
          action={strings.entry.undo}
          onAction={() => void form.undo()}
          onDismiss={form.dismissSaved}
        />
      ) : null}
    </div>
  )
}
