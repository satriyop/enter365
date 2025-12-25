<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('invoice_id')
                ->constrained('products')->nullOnDelete();
            $table->index('product_id');
        });

        Schema::table('bill_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('bill_id')
                ->constrained('products')->nullOnDelete();
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
