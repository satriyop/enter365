<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Template name for identification
            $table->string('type'); // invoice, bill
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();

            // Schedule settings
            $table->string('frequency'); // daily, weekly, monthly, quarterly, yearly
            $table->integer('interval')->default(1); // Every X frequency
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_generate_date')->nullable();
            $table->integer('occurrences_limit')->nullable(); // Max number of documents to generate
            $table->integer('occurrences_count')->default(0); // Number generated so far

            // Document template data
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(11.00);
            $table->bigInteger('discount_amount')->default(0);
            $table->decimal('early_discount_percent', 5, 2)->default(0);
            $table->integer('early_discount_days')->default(0);
            $table->integer('payment_term_days')->default(30);
            $table->string('currency', 3)->default('IDR');

            // Line items stored as JSON
            $table->json('items');

            // Settings
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_post')->default(false); // Auto-post when generated
            $table->boolean('auto_send')->default(false); // Auto-send notification

            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
            $table->index('next_generate_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_templates');
    }
};
