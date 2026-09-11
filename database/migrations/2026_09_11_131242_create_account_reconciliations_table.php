<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('partner_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->text('notes')->nullable();
            $table->timestamp('reconciled_at');
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'reconciled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_reconciliations');
    }
};
