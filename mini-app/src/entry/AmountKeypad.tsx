import { strings } from '../strings'

type AmountKeypadProps = {
  value: string
  onChange: (next: string) => void
}

// The keys read top-to-bottom, left-to-right like a phone dial pad. `.` reads as a
// thousands separator by the grammar in `parseAmount`, never a decimal point (see that
// file's doc comment), so it sits where a decimal key would be without pretending to be one.
const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', '⌫']

// A digit grid that writes a raw string into the hook; it knows nothing about currency
// or validity; `parseAmount` is the sole judge of what the accumulated string means.
export function AmountKeypad({ value, onChange }: AmountKeypadProps) {
  function press(key: string): void {
    onChange(key === '⌫' ? value.slice(0, -1) : value + key)
  }

  return (
    <div className="grid grid-cols-3 gap-2 p-4">
      {KEYS.map((key) => (
        <button
          key={key}
          type="button"
          aria-label={key === '⌫' ? strings.entry.backspace : key}
          onClick={() => press(key)}
          className="rounded-xl py-4 text-xl font-medium"
          style={{ background: 'var(--tg-secondary-bg)', color: 'var(--tg-text)' }}
        >
          {key}
        </button>
      ))}
    </div>
  )
}
