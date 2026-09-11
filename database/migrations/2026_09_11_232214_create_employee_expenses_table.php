<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 40)->unique();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->date('expense_date');
            $table->string('description', 500);
            $table->bigInteger('amount');
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('total_amount');
            $table->foreignId('expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expense_date']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_expenses');
    }
};
