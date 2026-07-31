<?php

namespace App\Models;

use App\Enums\RevisionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'transaction_id',
        'action',
        'actor_id',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'action' => RevisionAction::class,
            'snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
