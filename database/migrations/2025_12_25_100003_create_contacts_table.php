<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // e.g., "C-0001"
            $table->string('name');
            $table->string('type'); // customer, supplier, both
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('npwp', 30)->nullable(); // Indonesian Tax ID
            $table->string('nik', 20)->nullable(); // Indonesian ID Number
            $table->bigInteger('credit_limit')->default(0); // In IDR
            $table->integer('payment_term_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
