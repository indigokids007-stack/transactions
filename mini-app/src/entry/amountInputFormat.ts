// Shared by any amount text input that types straight into `parseAmount`'s grammar
// (`EntryScreen`'s own field, and the transaction edit form's amount field): grouping a
// pure-digit value for display, and undoing that grouping back to what `parseAmount`
// expects. Split out so both callers stay byte-for-byte identical rather than drifting.

// Groups a pure-digit string for display only (`120000` -> `120 000`). A value that
// isn't all digits (a magnitude word, a typed separator) passes through untouched, so
// `parseAmount`'s grammar keeps reading the exact characters the user typed.
export function groupDigitsForDisplay(raw: string): string {
  return /^\d+$/.test(raw) ? raw.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') : raw
}

// The field's `value` is the grouped display string above, so every keystroke's
// `event.target.value` already carries whichever grouping spaces the last render put
// there. Stripping whitespace undoes that grouping back to a clean digit run — but only
// when the result is pure digits: a magnitude word or a `.`-separator amount (`30 ming`,
// `12,50`) needs its own literal spacing to keep matching `parseAmount`'s grammar, so
// anything that doesn't collapse to a clean digit run passes through verbatim.
export function amountInputValue(raw: string): string {
  const stripped = raw.replace(/\s/g, '')
  return /^\d+$/.test(stripped) ? stripped : raw
}
