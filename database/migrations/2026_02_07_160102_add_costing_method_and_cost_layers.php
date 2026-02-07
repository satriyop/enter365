<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add costing_method to accounting_policies
        Schema::table('accounting_policies', function (Blueprint $table) {
            $table->string('costing_method')->default('weighted_average')->after('closing_strategy');
        });

        // Update existing row to weighted_average
        DB::table('accounting_policies')->update(['costing_method' => 'weighted_average']);

        // Create inventory cost layers table for FIFO tracking
        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->integer('quantity')->comment('Remaining quantity in this layer');
            $table->integer('original_quantity')->comment('Original quantity when layer was created');
            $table->bigInteger('unit_cost')->comment('Cost per unit in this layer (IDR)');
            $table->date('received_date')->comment('Date this batch was received');
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'received_date'], 'cost_layers_fifo_idx');
            $table->index(['product_id', 'warehouse_id', 'quantity'], 'cost_layers_available_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layers');

        Schema::table('accounting_policies', function (Blueprint $table) {
            $table->dropColumn('costing_method');
        });
    }
};
