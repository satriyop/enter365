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
        Schema::create('solar_proposals', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('proposal_number', 50)->unique();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired'])->default('draft');

            // Site Information
            $table->string('site_name')->nullable();
            $table->text('site_address')->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('roof_area_m2', 10, 2)->nullable();
            $table->enum('roof_type', ['flat', 'sloped', 'carport', 'ground'])->default('flat');
            $table->enum('roof_orientation', ['north', 'south', 'east', 'west', 'northeast', 'northwest', 'southeast', 'southwest'])->default('north');
            $table->decimal('roof_tilt_degrees', 5, 2)->default(10);
            $table->decimal('shading_percentage', 5, 2)->default(0);

            // Electricity Profile
            $table->decimal('monthly_consumption_kwh', 12, 2)->nullable();
            $table->string('pln_tariff_category', 20)->nullable();
            $table->integer('electricity_rate')->nullable(); // Rp per kWh
            $table->decimal('tariff_escalation_percent', 5, 2)->default(5.00);

            // Solar Data (from location lookup or manual)
            $table->decimal('peak_sun_hours', 5, 2)->nullable();
            $table->decimal('solar_irradiance', 8, 2)->nullable(); // kWh/m²/day
            $table->decimal('performance_ratio', 4, 2)->default(0.80);

            // System Selection
            $table->foreignId('variant_group_id')->nullable()->constrained('bom_variant_groups')->nullOnDelete();
            $table->foreignId('selected_bom_id')->nullable()->constrained('boms')->nullOnDelete();
            $table->decimal('system_capacity_kwp', 10, 2)->nullable();
            $table->decimal('annual_production_kwh', 14, 2)->nullable();

            // Calculated Results (stored as JSON for performance)
            $table->json('financial_analysis')->nullable(); // payback, roi, npv, irr, yearly_projections
            $table->json('environmental_impact')->nullable(); // co2_offset, trees_equivalent, car_equivalent

            // Proposal Settings
            $table->json('sections_config')->nullable(); // which sections to include, order
            $table->json('custom_content')->nullable(); // editable text sections
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();

            // Metadata
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('converted_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index(['contact_id', 'status']);
            $table->index('proposal_number');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solar_proposals');
    }
};
