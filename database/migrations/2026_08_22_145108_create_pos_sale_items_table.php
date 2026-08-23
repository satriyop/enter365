<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_inclusive');
            $table->unsignedBigInteger('payable_amount');
            $table->unsignedBigInteger('dpp_amount');
            $table->unsignedBigInteger('ppn_amount');
            $table->boolean('is_taxable');
            $table->boolean('track_inventory');
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements');
            $table->unsignedBigInteger('cogs_amount')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_items');
    }
};
