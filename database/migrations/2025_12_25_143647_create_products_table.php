<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();

            // Type: product (physical goods) or service
            $table->string('type', 20)->default('product'); // product, service

            // Category
            $table->foreignId('category_id')->nullable()
                ->constrained('product_categories')->nullOnDelete();

            // Unit of measure
            $table->string('unit', 20)->default('unit'); // unit, pcs, kg, liter, jam (hour), etc.

            // Pricing
            $table->bigInteger('purchase_price')->default(0); // Harga beli
            $table->bigInteger('selling_price')->default(0);  // Harga jual

            // Tax
            $table->decimal('tax_rate', 5, 2)->default(11.00); // PPN 11%
            $table->boolean('is_taxable')->default(true);

            // Inventory tracking (for products, not services)
            $table->boolean('track_inventory')->default(false);
            $table->integer('min_stock')->default(0);        // Minimum stock level for alerts
            $table->integer('current_stock')->default(0);    // Denormalized for quick access

            // Accounting links
            $table->foreignId('inventory_account_id')->nullable()
                ->constrained('accounts')->nullOnDelete();   // 1-1400 Persediaan
            $table->foreignId('cogs_account_id')->nullable()
                ->constrained('accounts')->nullOnDelete();   // 5-1000 Harga Pokok Penjualan
            $table->foreignId('sales_account_id')->nullable()
                ->constrained('accounts')->nullOnDelete();   // 4-1000 Penjualan
            $table->foreignId('purchase_account_id')->nullable()
                ->constrained('accounts')->nullOnDelete();   // 5-1000 or 1-1400

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_purchasable')->default(true);  // Can be used in bills
            $table->boolean('is_sellable')->default(true);     // Can be used in invoices

            // Additional info
            $table->string('barcode', 50)->nullable();
            $table->string('brand', 100)->nullable();
            $table->json('custom_fields')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_active']);
            $table->index(['type', 'is_active']);
            $table->index('sku');
            $table->index('barcode');
            $table->index('current_stock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
