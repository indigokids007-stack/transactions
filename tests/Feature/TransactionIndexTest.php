<?php

use Illuminate\Support\Facades\DB;

/**
 * Every read path applies the soft delete scope, so a full index carries rows no query can
 * be answered from while still paying for them on every write. The spec asked for these to
 * be partial; they were created full.
 */
it('keeps every transaction index partial on the rows that are not deleted', function () {
    $definitions = DB::table('pg_indexes')
        ->where('tablename', 'transactions')
        ->pluck('indexdef', 'indexname');

    $expected = [
        'transactions_user_id_occurred_on_index',
        'transactions_department_id_occurred_on_index',
        'transactions_occurred_on_index',
        'transactions_category_id_index',
    ];

    foreach ($expected as $name) {
        expect($definitions->get($name))
            ->not->toBeNull("index {$name} is missing")
            ->and($definitions->get($name))->toContain('WHERE (deleted_at IS NULL)');
    }
});
