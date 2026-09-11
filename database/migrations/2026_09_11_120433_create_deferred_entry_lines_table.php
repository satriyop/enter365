<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deferred_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deferred_entry_id')->constrained('deferred_entries')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('recognition_date');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('remaining_amount');
            $table->string('status', 20)->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['deferred_entry_id', 'sequence']);
            $table->index(['recognition_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deferred_entry_lines');
    }
};
