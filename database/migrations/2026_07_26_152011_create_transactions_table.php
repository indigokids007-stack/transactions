<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->date('occurred_on');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'occurred_on']);
            $table->index(['department_id', 'occurred_on']);
            $table->index('occurred_on');
            $table->index('category_id');
        });
    }
};
