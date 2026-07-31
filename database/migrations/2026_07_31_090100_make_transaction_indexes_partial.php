<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The spec asks for these four indexes to be partial on `WHERE deleted_at IS NULL`; they
 * were created full. Every read path applies the soft delete scope, so the deleted rows an
 * index carried could never be answered from it, and they cost write time and space on
 * every insert. Postgres only, which the spec already commits to, and expressed as raw
 * statements because the schema builder has no partial index.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const INDEXES = [
        'transactions_user_id_occurred_on_index' => '(user_id, occurred_on)',
        'transactions_department_id_occurred_on_index' => '(department_id, occurred_on)',
        'transactions_occurred_on_index' => '(occurred_on)',
        'transactions_category_id_index' => '(category_id)',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $columns) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
            DB::statement("CREATE INDEX {$name} ON transactions {$columns} WHERE deleted_at IS NULL");
        }
    }
};
