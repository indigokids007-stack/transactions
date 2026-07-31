<?php

namespace App\Console\Commands;

use App\Models\EntryDraft;
use Illuminate\Console\Command;

/**
 * A draft nobody confirmed is abandoned, not pending: once it has expired it can never
 * become a transaction, so it is swept away rather than kept.
 */
class PruneEntryDraftsCommand extends Command
{
    protected $signature = 'transactions:prune-drafts';

    protected $description = 'Delete entry drafts that expired without being confirmed';

    public function handle(): int
    {
        $deleted = EntryDraft::query()->where('expires_at', '<', now())->delete();

        $this->comment("Deleted {$deleted} expired entry draft(s).");

        return self::SUCCESS;
    }
}
