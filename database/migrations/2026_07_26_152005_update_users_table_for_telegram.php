<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_id')->unique()->after('id');
            $table->string('username')->nullable()->after('name');
            $table->string('role')->default('staff')->after('username');
            $table->string('status')->default('pending')->after('role');
            $table->string('locale', 2)->default('ru')->after('status');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }
};
