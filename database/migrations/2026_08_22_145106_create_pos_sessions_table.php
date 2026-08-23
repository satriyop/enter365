<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_number')->unique();
            $table->string('status', 20);
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('cash_account_id')->constrained('accounts');
            $table->foreignId('qris_account_id')->constrained('accounts');
            $table->unsignedBigInteger('opening_cash_amount');
            $table->unsignedBigInteger('expected_cash_amount')->nullable();
            $table->unsignedBigInteger('counted_cash_amount')->nullable();
            $table->bigInteger('cash_difference_amount')->nullable();
            $table->foreignId('opened_by')->constrained('users');
            $table->timestamp('opened_at');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
    }
};
