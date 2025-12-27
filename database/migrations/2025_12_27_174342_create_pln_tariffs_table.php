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
        Schema::create('pln_tariffs', function (Blueprint $table) {
            $table->id();

            // Tariff Classification
            $table->string('category_code', 20)->unique(); // e.g., R-1/TR, I-3/TM, B-2/TR
            $table->string('category_name', 100); // e.g., "Rumah Tangga", "Industri", "Bisnis"
            $table->enum('customer_type', ['residential', 'industrial', 'business', 'social', 'government'])->default('industrial');

            // Power Range
            $table->integer('power_va_min'); // VA minimum
            $table->integer('power_va_max')->nullable(); // VA maximum (null = unlimited)

            // Pricing
            $table->integer('rate_per_kwh'); // Rp per kWh (stored as integer)
            $table->integer('capacity_charge')->nullable(); // Rp per kVA per month
            $table->integer('minimum_charge')->nullable(); // Minimum monthly charge

            // Time-of-Use (TOU) rates if applicable
            $table->integer('peak_rate_per_kwh')->nullable(); // Peak hours rate
            $table->integer('off_peak_rate_per_kwh')->nullable(); // Off-peak rate
            $table->string('peak_hours', 50)->nullable(); // e.g., "18:00-22:00"

            // Status
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('customer_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pln_tariffs');
    }
};
