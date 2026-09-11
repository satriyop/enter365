<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytic_distribution_models', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->foreignId('partner_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('account_prefix', 30)->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('analytic_distribution');
            $table->unsignedSmallInteger('sequence')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytic_distribution_models');
    }
};
