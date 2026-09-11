<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 40)->unique();
            $table->date('transfer_date');
            $table->foreignId('from_journal_id')->constrained('journals');
            $table->foreignId('to_journal_id')->constrained('journals');
            $table->foreignId('from_account_id')->constrained('accounts');
            $table->foreignId('to_account_id')->constrained('accounts');
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('draft');
            $table->text('memo')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_transfers');
    }
};
