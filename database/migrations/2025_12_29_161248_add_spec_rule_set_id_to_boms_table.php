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
        Schema::table('boms', function (Blueprint $table) {
            $table->foreignId('spec_rule_set_id')
                ->nullable()
                ->after('variant_group_id')
                ->constrained('spec_validation_rule_sets')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boms', function (Blueprint $table) {
            $table->dropForeign(['spec_rule_set_id']);
            $table->dropColumn('spec_rule_set_id');
        });
    }
};
