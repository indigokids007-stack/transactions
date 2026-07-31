<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->jsonb('snapshot');
            $table->timestamp('created_at');

            $table->index(['transaction_id', 'created_at']);
        });
    }
};
