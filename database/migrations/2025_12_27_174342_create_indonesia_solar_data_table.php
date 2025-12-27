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
        Schema::create('indonesia_solar_data', function (Blueprint $table) {
            $table->id();

            // Location
            $table->string('province', 100);
            $table->string('city', 100);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);

            // Solar Data
            $table->decimal('peak_sun_hours', 5, 2); // Annual average hours/day
            $table->decimal('solar_irradiance_kwh_m2_day', 6, 3); // kWh/m²/day
            $table->decimal('optimal_tilt_angle', 5, 2)->default(10); // degrees

            // Optional extended data
            $table->decimal('ghi_annual', 10, 2)->nullable(); // Global Horizontal Irradiance kWh/m²/year
            $table->decimal('dni_annual', 10, 2)->nullable(); // Direct Normal Irradiance kWh/m²/year
            $table->decimal('dhi_annual', 10, 2)->nullable(); // Diffuse Horizontal Irradiance kWh/m²/year
            $table->decimal('temperature_avg', 5, 2)->nullable(); // Average temperature °C

            $table->timestamps();

            // Indexes
            $table->unique(['province', 'city']);
            $table->index('province');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indonesia_solar_data');
    }
};
