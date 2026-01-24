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
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number', 30)->unique();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->date('opname_date');

            // Status: draft, counting, reviewed, approved, completed, cancelled
            $table->string('status', 20)->default('draft');

            $table->string('name')->nullable(); // e.g., "Year End Count 2024"
            $table->text('notes')->nullable();

            // Workflow tracking
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('counting_started_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Summary (calculated)
            $table->integer('total_items')->default(0);
            $table->integer('total_counted')->default(0);
            $table->integer('total_variance_qty')->default(0);
            $table->bigInteger('total_variance_value')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('opname_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
