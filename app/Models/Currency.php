<?php

namespace App\Models;

use App\Contracts\ReferencedByLedger;
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Currency extends Model implements ReferencedByLedger
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    private const EXPONENTS_CACHE_KEY = 'currencies.exponents';

    protected $fillable = [
        'code',
        'name',
        'exponent',
        'is_active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'exponent' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::EXPONENTS_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::EXPONENTS_CACHE_KEY));
    }

    /**
     * Every currency's decimal exponent, active or retired: a retired currency still has
     * to format the historical transactions that were written in it. Cached because
     * `Money` calls this once per amount, and a report or an export can format hundreds
     * in a single request.
     *
     * @return array<string, int>
     */
    public static function exponents(): array
    {
        return Cache::rememberForever(self::EXPONENTS_CACHE_KEY, fn () => static::query()->pluck('exponent', 'code')->all());
    }

    public function isReferencedByLedger(): bool
    {
        return DB::table('transactions')->where('currency', $this->code)->exists();
    }
}
