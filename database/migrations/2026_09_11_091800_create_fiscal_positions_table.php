<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_positions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 200);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fiscal_position_tax_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_position_id')->constrained('fiscal_positions')->cascadeOnDelete();
            $table->foreignId('source_tax_record_id')->constrained('tax_records')->restrictOnDelete();
            $table->foreignId('dest_tax_record_id')->nullable()->constrained('tax_records')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['fiscal_position_id', 'source_tax_record_id'], 'fp_tax_maps_position_source_unique');
        });

        Schema::create('fiscal_position_account_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_position_id')->constrained('fiscal_positions')->cascadeOnDelete();
            $table->foreignId('source_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('dest_account_id')->constrained('accounts')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['fiscal_position_id', 'source_account_id'], 'fp_account_maps_position_source_unique');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('fiscal_position_id')
                ->nullable()
                ->after('is_foreign_entity')
                ->constrained('fiscal_positions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fiscal_position_id');
        });
        Schema::dropIfExists('fiscal_position_account_maps');
        Schema::dropIfExists('fiscal_position_tax_maps');
        Schema::dropIfExists('fiscal_positions');
    }
};
