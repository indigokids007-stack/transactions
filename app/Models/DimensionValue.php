<?php

namespace App\Models;

use App\Contracts\ReferencedByLedger;
use Database\Factories\DimensionValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

/** @property-read Pivot $pivot */
class DimensionValue extends Model implements ReferencedByLedger
{
    /** @use HasFactory<DimensionValueFactory> */
    use HasFactory;

    protected $fillable = [
        'dimension_id',
        'name',
        'is_active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Dimension, $this> */
    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }

    public function isReferencedByLedger(): bool
    {
        return DB::table('transaction_dimension_values')->where('dimension_value_id', $this->id)->exists();
    }
}
