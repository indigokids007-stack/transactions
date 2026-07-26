<?php

namespace App\Models;

use Database\Factories\DimensionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dimension extends Model
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
}
