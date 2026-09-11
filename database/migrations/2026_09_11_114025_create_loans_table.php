<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 200);
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->unsignedBigInteger('principal');
            $table->decimal('annual_interest_rate', 8, 4)->default(0);
            $table->unsignedSmallInteger('duration_months');
            $table->date('start_date');
            $table->foreignId('liability_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('interest_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('remaining_principal')->default(0);
            $table->foreignId('disbursement_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
