<?php

namespace App\Models;

use App\Enums\CategoryAppliesTo;
use App\Enums\TransactionType;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'applies_to',
        'is_active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'applies_to' => CategoryAppliesTo::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function acceptsType(TransactionType $type): bool
    {
        if ($this->applies_to === CategoryAppliesTo::Both) {
            return true;
        }

        return $this->applies_to->value === $type->value;
    }
}
