<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two admin clicks used to destroy ledger history without leaving a revision behind.
 *
 * Deleting a department set `department_id` to null on every transaction ever charged to
 * it, which both erased the charge and moved those rows out of the department branch of
 * `TransactionScope`, quietly changing who could see them. Deleting a dimension or one of
 * its values removed the pivot rows recording what was chosen; a revision snapshot stores
 * bare value ids, so nothing could reconstruct what had been there.
 *
 * Categories and users were already restricted. These were the two remaining paths that
 * reach into `transactions`, and they are closed the same way: retiring any of them means
 * deactivating it.
 *
 * `transaction_id` on the pivot deliberately keeps its cascade. Transactions are soft
 * deleted and never removed, and if one ever genuinely were, its pivot rows describe
 * nothing and should go with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['department_id']);

            $table->foreign('department_id')
                ->references('id')
                ->on('departments')
                ->restrictOnDelete();
        });

        Schema::table('transaction_dimension_values', function (Blueprint $table) {
            $table->dropForeign(['dimension_id']);

            $table->foreign('dimension_id')
                ->references('id')
                ->on('dimensions')
                ->restrictOnDelete();

            $table->dropForeign(['dimension_value_id']);

            $table->foreign('dimension_value_id')
                ->references('id')
                ->on('dimension_values')
                ->restrictOnDelete();
        });
    }
};
