<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'department_id',
        'type',
        'amount_minor',
        'currency',
        'occurred_on',
        'category_id',
        'note',
        'created_by',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'occurred_on' => 'date',
            'amount_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasMany<TransactionRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(TransactionRevision::class);
    }

    /** @return BelongsToMany<DimensionValue, $this> */
    public function dimensionValues(): BelongsToMany
    {
        return $this->belongsToMany(DimensionValue::class, 'transaction_dimension_values')
            ->withPivot('dimension_id');
    }
}
