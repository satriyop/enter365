<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nsfp_ranges', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 3);
            $table->string('branch_code', 3);
            $table->string('year_code', 2);
            $table->unsignedInteger('range_start');
            $table->unsignedInteger('range_end');
            $table->unsignedInteger('next_number');
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['transaction_code', 'branch_code', 'year_code', 'range_start'],
                'nsfp_ranges_unique_range'
            );

            $table->index(['is_active', 'year_code'], 'nsfp_ranges_active_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nsfp_ranges');
    }
};
