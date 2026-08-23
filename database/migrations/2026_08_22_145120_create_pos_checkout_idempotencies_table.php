<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_checkout_idempotencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_session_id')->constrained('pos_sessions')->cascadeOnDelete();
            $table->string('idempotency_key');
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pos_session_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_checkout_idempotencies');
    }
};
