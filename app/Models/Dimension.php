<?php

namespace App\Models;

use App\Contracts\ReferencedByLedger;
use Database\Factories\DimensionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Dimension extends Model implements ReferencedByLedger
{
    /** @use HasFactory<DimensionFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'is_required',
        'is_active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<DimensionValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(DimensionValue::class)->orderBy('sort');
    }

    /**
     * Asked of the pivot rather than of transactions, because the pivot row is what the
     * foreign key restricts and what carries the choice this dimension recorded.
     */
    public function isReferencedByLedger(): bool
    {
        return DB::table('transaction_dimension_values')->where('dimension_id', $this->id)->exists();
    }
}
