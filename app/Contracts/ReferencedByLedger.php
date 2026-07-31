<?php

namespace App\Contracts;

/**
 * A reference record that a written transaction can point at. The ledger is written once
 * and only ever added to, and a revision snapshot stores bare ids, so anything a
 * transaction points at cannot be deleted without losing history no audit trail can
 * reconstruct. Retiring one of these means deactivating it, never deleting it, and both
 * the database constraints and the panel enforce that.
 */
interface ReferencedByLedger
{
    /** Whether any transaction, live or soft deleted, still points at this record. */
    public function isReferencedByLedger(): bool;
}
