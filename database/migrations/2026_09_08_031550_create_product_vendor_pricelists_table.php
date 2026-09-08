<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('purchase_control_policy', 20)->default('received');
            $table->text('purchase_description')->nullable();
        });

        Schema::create('product_vendor_pricelists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->decimal('min_qty', 12, 4)->default(1);
            $table->string('unit', 20);
            $table->integer('price');
            $table->string('currency', 3)->default('IDR');
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->string('vendor_product_code')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_vendor_pricelists');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['purchase_control_policy', 'purchase_description']);
        });
    }
};
