<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix type mismatch: migration had decimal(12,4) but model casts to integer.
     * Since all quantities in this system are whole numbers (bigInteger pattern),
     * align the column to integer.
     */
    public function up(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->integer('reserved_quantity')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->decimal('reserved_quantity', 12, 4)->default(0)->change();
        });
    }
};
