<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates infrastructure for Multi-BOM Comparison feature:
     * - bom_variant_groups: Groups multiple BOM variants for side-by-side comparison
     * - Adds variant fields to boms table for categorization
     */
    public function up(): void
    {
        // Create variant groups table
        Schema::create('bom_variant_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "Material Options for PLTS 50kWp"
            $table->text('description')->nullable();
            $table->text('comparison_notes')->nullable(); // Summary of key differences
            $table->string('status')->default('draft'); // draft, active, archived
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });

        // Add variant fields to boms table
        Schema::table('boms', function (Blueprint $table) {
            $table->foreignId('variant_group_id')
                ->nullable()
                ->after('parent_bom_id')
                ->constrained('bom_variant_groups')
                ->onDelete('set null');
            $table->string('variant_name')->nullable()->after('variant_group_id'); // Budget, Standard, Premium
            $table->string('variant_label')->nullable()->after('variant_name'); // "Growatt + NUSA"
            $table->boolean('is_primary_variant')->default(false)->after('variant_label');
            $table->unsignedInteger('variant_sort_order')->default(0)->after('is_primary_variant');

            $table->index('variant_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boms', function (Blueprint $table) {
            $table->dropForeign(['variant_group_id']);
            $table->dropColumn([
                'variant_group_id',
                'variant_name',
                'variant_label',
                'is_primary_variant',
                'variant_sort_order',
            ]);
        });

        Schema::dropIfExists('bom_variant_groups');
    }
};
