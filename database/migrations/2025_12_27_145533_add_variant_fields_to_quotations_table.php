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
        // Add variant fields to quotations table
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('quotation_type')->default('single')->after('subject');
            $table->foreignId('variant_group_id')->nullable()->after('quotation_type')
                ->constrained('bom_variant_groups')->onDelete('set null');
            $table->foreignId('selected_variant_id')->nullable()->after('variant_group_id')
                ->constrained('boms')->onDelete('set null');

            $table->index(['quotation_type']);
            $table->index(['variant_group_id']);
        });

        // Create quotation_variant_options table for customer-facing variant details
        Schema::create('quotation_variant_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->onDelete('cascade');
            $table->foreignId('bom_id')->constrained()->onDelete('cascade');
            $table->string('display_name');
            $table->string('tagline')->nullable();
            $table->boolean('is_recommended')->default(false);
            $table->bigInteger('selling_price');
            $table->json('features')->nullable();
            $table->json('specifications')->nullable();
            $table->string('warranty_terms')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['quotation_id', 'bom_id']);
            $table->index(['quotation_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_variant_options');

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['variant_group_id']);
            $table->dropForeign(['selected_variant_id']);
            $table->dropIndex(['quotation_type']);
            $table->dropIndex(['variant_group_id']);
            $table->dropColumn(['quotation_type', 'variant_group_id', 'selected_variant_id']);
        });
    }
};
