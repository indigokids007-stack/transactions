<?php

namespace App\Models;

use App\Contracts\ReferencedByLedger;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model implements ReferencedByLedger
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_manager');
    }

    /**
     * Soft deleted transactions count. The foreign key restricts on the row still being
     * there, not on it being visible, and a deleted transaction is exactly the history a
     * revision would need to explain.
     */
    public function isReferencedByLedger(): bool
    {
        return Transaction::withTrashed()->where('department_id', $this->id)->exists();
    }
}
