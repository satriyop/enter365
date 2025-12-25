<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3); // Source currency code
            $table->string('to_currency', 3)->default('IDR'); // Target currency (base)
            $table->decimal('rate', 15, 4); // Exchange rate
            $table->date('effective_date'); // Date this rate is effective
            $table->string('source')->default('manual'); // manual, bank_indonesia, api
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['from_currency', 'to_currency', 'effective_date']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
