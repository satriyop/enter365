<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique(); // e.g., "BILL-202412-0001"
            $table->string('vendor_invoice_number')->nullable(); // Supplier's invoice number
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->date('bill_date');
            $table->date('due_date');
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(11.00);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->bigInteger('paid_amount')->default(0);
            $table->string('status')->default('draft'); // draft, received, partial, paid, overdue, cancelled
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payable_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('bill_date');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
