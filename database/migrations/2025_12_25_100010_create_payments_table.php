<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique(); // e.g., "PAY-202412-0001"
            $table->string('type'); // receive (from customer), send (to supplier)
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->date('payment_date');
            $table->bigInteger('amount')->default(0);
            $table->string('payment_method')->default('transfer'); // cash, transfer, check, giro
            $table->string('reference')->nullable(); // Check number, transfer ref, etc.
            $table->text('notes')->nullable();
            $table->foreignId('cash_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payable_type')->nullable(); // App\Models\Accounting\Invoice or Bill
            $table->unsignedBigInteger('payable_id')->nullable();
            $table->boolean('is_voided')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('payment_date');
            $table->index(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
