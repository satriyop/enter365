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
        Schema::create('bom_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('bom_templates')->cascadeOnDelete();
            $table->enum('type', ['material', 'labor', 'overhead'])->default('material');
            $table->foreignId('component_standard_id')->nullable()->constrained('component_standards')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('default_quantity', 10, 2)->default(1);
            $table->string('unit', 20)->default('pcs');
            $table->boolean('is_required')->default(true);
            $table->boolean('is_quantity_variable')->default(false);  // User can change qty
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bom_template_items');
    }
};
