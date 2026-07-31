<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_dimension_values', function (Blueprint $table) {
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dimension_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dimension_value_id')->constrained()->cascadeOnDelete();

            $table->primary(['transaction_id', 'dimension_id']);
            $table->index(['dimension_value_id', 'transaction_id']);
        });
    }
};
