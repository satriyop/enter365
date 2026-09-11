<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_roundings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->unsignedInteger('rounding')->default(100);
            $table->string('strategy', 20)->default('half_up');
            $table->foreignId('profit_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('loss_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_roundings');
    }
};
