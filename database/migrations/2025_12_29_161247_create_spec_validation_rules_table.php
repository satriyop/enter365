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
        Schema::create('spec_validation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_set_id')->constrained('spec_validation_rule_sets')->cascadeOnDelete();
            $table->string('category', 50);  // circuit_breaker, cable, contactor, etc.
            $table->string('spec_key', 50);  // breaking_capacity_ka, poles, conductor_size_mm2
            $table->enum('validation_type', [
                'warn_if_lower',   // Warn if new value < original
                'warn_if_higher',  // Warn if new value > original
                'must_match',      // Values must be equal
                'min_value',       // Must be >= threshold
                'max_value',       // Must be <= threshold
            ]);
            $table->decimal('threshold_value', 10, 2)->nullable();  // For min_value/max_value
            $table->enum('severity', ['warning', 'error'])->default('warning');
            $table->text('message')->nullable();  // Custom validation message
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->unique(['rule_set_id', 'category', 'spec_key'], 'unique_rule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spec_validation_rules');
    }
};
