<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_reconciliation_id')->constrained('account_reconciliations')->cascadeOnDelete();
            $table->foreignId('journal_entry_line_id')->constrained('journal_entry_lines');
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(
                ['account_reconciliation_id', 'journal_entry_line_id'],
                'acct_recon_items_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_reconciliation_items');
    }
};
