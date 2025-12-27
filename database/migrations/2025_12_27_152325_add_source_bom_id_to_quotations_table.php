<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('source_bom_id')
                ->nullable()
                ->after('selected_variant_id')
                ->constrained('boms')
                ->nullOnDelete();

            $table->index('source_bom_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['source_bom_id']);
            $table->dropIndex(['source_bom_id']);
            $table->dropColumn('source_bom_id');
        });
    }
};
