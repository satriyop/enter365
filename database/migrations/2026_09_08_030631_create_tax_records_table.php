<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_records', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->decimal('rate', 5, 2);
            $table->string('applicability', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_tax_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('tax_record_id')->constrained('tax_records')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->timestamps();

            $table->unique(['product_id', 'tax_record_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tax_records');
        Schema::dropIfExists('tax_records');
    }
};
