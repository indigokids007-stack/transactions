import { strings } from '../strings'
import { parseQuantity } from './parseQuantity'

type Props = { value: string; onChange: (value: string) => void }

export function QuantityField({ value, onChange }: Props) {
  const invalid = value.trim() !== '' && parseQuantity(value) === null
  return (
    <div style={{ background: 'var(--field)', borderRadius: 18, padding: '12px 16px' }}>
      <label className="flex items-center justify-between gap-3">
        <span style={{ font: '600 13px/1.4 "Plus Jakarta Sans"', color: 'var(--teal-900)' }}>{strings.entry.quantity}</span>
        <input type="text" inputMode="decimal" value={value} onChange={(event) => onChange(event.target.value)}
          placeholder={strings.entry.quantityPlaceholder} aria-invalid={invalid}
          style={{ minWidth: 0, width: 140, textAlign: 'right', border: 0, background: 'transparent', font: '600 16px/1.4 "Plus Jakarta Sans"', color: 'var(--ink)', outline: 'none' }} />
      </label>
      {invalid ? <p role="alert" className="mt-2 text-sm" style={{ color: 'var(--expense)' }}>{strings.entry.invalidQuantity}</p> : null}
    </div>
  )
}
