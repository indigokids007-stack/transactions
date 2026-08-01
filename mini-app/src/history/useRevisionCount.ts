import type { ApiClient } from '../api/client'
import { useReportFetch } from '../reports/useReportFetch'

export type RevisionCountState =
  | { status: 'loading' }
  | { status: 'loaded'; count: number }
  | { status: 'failed'; retry: () => void }

// A genuine external read (the revisions endpoint), so built on the same fetch-with-retry
// effect `SummaryView`/`TrendView`/`StaffView` already share (`useReportFetch`) rather than
// a bespoke one: same stale-response guard (`ignore`), same retry trigger. Loading and
// failure used to both collapse to `null`, which read to the sheet as one indistinguishable
// placeholder with nothing the user could do about a failure — this returns the three
// states explicitly instead, `failed` carrying its own `retry`.
export function useRevisionCount(client: Pick<ApiClient, 'revisions'>, transactionId: number): RevisionCountState {
  const { data, failed, retry } = useReportFetch(() => client.revisions(transactionId), [client, transactionId])

  if (failed) return { status: 'failed', retry }
  if (data === null) return { status: 'loading' }
  return { status: 'loaded', count: data.length }
}
