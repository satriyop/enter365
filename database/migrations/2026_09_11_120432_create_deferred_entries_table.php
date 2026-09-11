<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deferred_entries', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('code', 30)->unique();
            $table->string('name', 200);
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedSmallInteger('duration_months');
            $table->date('start_date');
            $table->foreignId('deferred_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('recognition_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('counterpart_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('remaining_amount')->default(0);
            $table->foreignId('origination_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deferred_entries');
    }
};
