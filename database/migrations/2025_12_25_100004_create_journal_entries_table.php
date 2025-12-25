<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number')->unique(); // e.g., "JE-202412-0001"
            $table->date('entry_date');
            $table->text('description');
            $table->string('reference')->nullable(); // Reference to source document
            $table->string('source_type')->nullable(); // invoice, bill, payment, manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('fiscal_period_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_posted')->default(false);
            $table->boolean('is_reversed')->default(false);
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('entry_date');
            $table->index('is_posted');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
