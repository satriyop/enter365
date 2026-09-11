<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_depreciation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('depreciation_date');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('depreciated_value');
            $table->unsignedBigInteger('remaining_value');
            $table->string('status', 20)->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'sequence'], 'asset_depr_lines_asset_sequence_unique');
            $table->index(['depreciation_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciation_lines');
    }
};
