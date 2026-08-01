import type { ApiTransaction, ApiUser } from '../api/types'

// A client-side mirror of `App\Policies\TransactionPolicy::update` (and, since `delete`
// on that policy is defined as calling `update`, of `destroy` too): an admin may touch
// anything, an owner may touch nothing — not even their own record — and everyone else
// only what they wrote themselves. Deliberately narrower than what a row's *visibility*
// already allows: a manager can see a colleague's department-mates' spend and an owner
// can see the whole ledger, but seeing is not writing, so this check is not derived from
// whatever filtered `items` down to what render sees.
//
// This is a UI convenience, not the authority: it only decides whether to *offer* edit
// and delete, so an offer is never made for an action the server is already known to
// refuse. The server re-checks the same policy on every `PATCH`/`DELETE` regardless —
// see `TransactionSheet`'s own doc comment for what happens when it still says no.
export function canManageTransaction(user: ApiUser, transaction: ApiTransaction): boolean {
  if (user.role === 'admin') return true
  if (user.role === 'owner') return false

  return transaction.user.id === user.id
}
