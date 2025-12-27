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
        Schema::create('component_brand_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_standard_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('brand', 50);
            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('brand_sku', 100)->comment('Vendor catalog SKU');
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->decimal('price_factor', 5, 2)->default(1.00);
            $table->json('variant_specs')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['component_standard_id', 'product_id']);
            $table->unique(['component_standard_id', 'brand', 'brand_sku']);
            $table->index('brand');
            $table->index(['component_standard_id', 'brand', 'is_preferred']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('component_brand_mappings');
    }
};
