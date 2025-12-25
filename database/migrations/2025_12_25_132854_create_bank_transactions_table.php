<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete(); // Bank account
            $table->date('transaction_date');
            $table->string('description');
            $table->string('reference')->nullable(); // Bank reference/check number
            $table->bigInteger('debit')->default(0); // Money in
            $table->bigInteger('credit')->default(0); // Money out
            $table->bigInteger('balance')->default(0); // Running balance from bank

            // Reconciliation
            $table->string('status')->default('unmatched'); // unmatched, matched, reconciled
            $table->foreignId('matched_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('matched_journal_line_id')->nullable()->constrained('journal_entry_lines')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();

            // Import tracking
            $table->string('import_batch')->nullable(); // Batch ID for imported statements
            $table->string('external_id')->nullable(); // ID from bank statement

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'transaction_date']);
            $table->index('status');
            $table->index('import_batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
