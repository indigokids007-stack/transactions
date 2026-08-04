<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `config/money.php` used to be the only list of currencies a transaction could carry.
 * This seeds that same list into `currencies` so every existing `transactions.currency`
 * value still resolves, then constrains the column the same way `category_id` and
 * `department_id` already are: a currency in use cannot be deleted, only deactivated.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('currencies')->insert([
            ['code' => 'UZS', 'name' => 'Uzbekistan Som', 'exponent' => 0, 'is_active' => true, 'sort' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'USD', 'name' => 'US Dollar', 'exponent' => 2, 'is_active' => true, 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EUR', 'name' => 'Euro', 'exponent' => 2, 'is_active' => true, 'sort' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'RUB', 'name' => 'Russian Ruble', 'exponent' => 2, 'is_active' => true, 'sort' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
        });
    }
};
