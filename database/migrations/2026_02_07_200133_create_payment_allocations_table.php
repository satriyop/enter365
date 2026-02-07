<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('allocatable_type');
            $table->unsignedBigInteger('allocatable_id');
            $table->bigInteger('amount'); // in document currency
            $table->timestamps();

            $table->unique(['payment_id', 'allocatable_type', 'allocatable_id']);
            $table->index(['allocatable_type', 'allocatable_id']);
        });

        // Make payable_type/payable_id nullable (they already are in schema,
        // but ensure consistency for future single-allocation backward compat)
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
