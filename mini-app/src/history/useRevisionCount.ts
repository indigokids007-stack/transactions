import { useEffect, useState } from 'react'
import type { ApiClient } from '../api/client'

// `null` while the count hasn't arrived yet (loading) or the call failed — the sheet
// reads either as "nothing to show", since a placeholder guess would be worse than no
// number at all. A genuine external read (the revisions endpoint), so a real effect, the
// same as `SummaryView`'s own fetch: the `ignore` flag keeps a slow response for a row
// the user has since closed from writing into state nothing is listening to anymore.
export function useRevisionCount(client: Pick<ApiClient, 'revisions'>, transactionId: number): number | null {
  const [count, setCount] = useState<number | null>(null)

  useEffect(() => {
    let ignore = false
    setCount(null)

    client
      .revisions(transactionId)
      .then((revisions) => {
        if (!ignore) setCount(revisions.length)
      })
      .catch(() => {
        if (!ignore) setCount(null)
      })

    return () => {
      ignore = true
    }
  }, [client, transactionId])

  return count
}
