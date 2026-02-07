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
        Schema::table('contacts', function (Blueprint $table) {
            $table->decimal('early_discount_percent', 5, 2)->default(0)->nullable()->change();
            $table->integer('early_discount_days')->default(0)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->decimal('early_discount_percent', 5, 2)->nullable(false)->default(null)->change();
            $table->integer('early_discount_days')->nullable(false)->default(null)->change();
        });
    }
};
