// The bracketed form `dimension[<key>]` is how `TransactionFilterRequest` reads a
// dimension filter (`$this->input('dimension')` as an associative array); every other
// filter is a flat query parameter.
function appendParams(search: URLSearchParams, params: Record<string, unknown>): void {
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null) continue

    if (key === 'dimension' && typeof value === 'object') {
      for (const [dimensionKey, dimensionValue] of Object.entries(value as Record<string, unknown>)) {
        if (dimensionValue === undefined || dimensionValue === null) continue
        search.append(`dimension[${dimensionKey}]`, String(dimensionValue))
      }
      continue
    }

    search.append(key, String(value))
  }
}

export function toQueryString(params: Record<string, unknown>): string {
  const search = new URLSearchParams()
  appendParams(search, params)
  const query = search.toString()
  return query === '' ? '' : `?${query}`
}
