<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_number', 30)->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();

            // Movement type
            $table->string('type', 20); // in, out, adjustment, transfer_in, transfer_out

            // Quantities
            $table->integer('quantity');              // + for in, - for out
            $table->integer('quantity_before');       // Stock before movement
            $table->integer('quantity_after');        // Stock after movement

            // Costing
            $table->bigInteger('unit_cost')->default(0);     // Cost per unit
            $table->bigInteger('total_cost')->default(0);    // quantity * unit_cost

            // Reference to source document
            $table->string('reference_type')->nullable();    // Invoice, Bill, StockAdjustment, Transfer
            $table->unsignedBigInteger('reference_id')->nullable();

            // For transfers between warehouses
            $table->foreignId('transfer_warehouse_id')->nullable()
                ->constrained('warehouses')->nullOnDelete();

            $table->date('movement_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['product_id', 'warehouse_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('movement_date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
