<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_pph_subject')->default(false)->after('is_subcontractor');
            $table->string('pph_category', 30)->nullable()->after('is_pph_subject');
            $table->decimal('pph_rate', 5, 2)->nullable()->after('pph_category');
            $table->boolean('is_foreign_entity')->default(false)->after('pph_rate');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['is_pph_subject', 'pph_category', 'pph_rate', 'is_foreign_entity']);
        });
    }
};
