<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique();
            $table->foreignId('pos_session_id')->constrained('pos_sessions');
            $table->string('status', 20);
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('dpp_amount');
            $table->unsignedBigInteger('ppn_amount');
            $table->unsignedBigInteger('payable_amount');
            $table->unsignedBigInteger('cash_received_amount')->default(0);
            $table->unsignedBigInteger('change_amount')->default(0);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->foreignId('cogs_journal_entry_id')->nullable()->constrained('journal_entries');
            $table->timestamp('sold_at');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->string('void_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
    }
};
