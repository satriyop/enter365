<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stock per product per warehouse
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->bigInteger('average_cost')->default(0); // For weighted average costing
            $table->bigInteger('total_value')->default(0);  // quantity * average_cost
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
            $table->index('quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
    }
};
