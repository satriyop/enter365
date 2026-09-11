<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->json('lines');
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->string('direction', 16);
            $table->string('payment_type', 32);
            $table->foreignId('journal_id')->nullable()->constrained('journals')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->string('state', 16)->default('disabled');
            $table->foreignId('journal_id')->nullable()->constrained('journals')->restrictOnDelete();
            $table->string('website')->nullable();
            $table->timestamps();
        });

        Schema::create('check_settings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->foreignId('journal_id')->unique()->constrained('journals')->restrictOnDelete();
            $table->unsignedInteger('next_number')->default(1);
            $table->string('layout', 16)->default('top');
            $table->boolean('manual_numbering')->default(false);
            $table->timestamps();
        });
        Schema::create('payment_method_payment_provider', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_provider_id')->constrained()->cascadeOnDelete();
            $table->primary(['payment_method_id', 'payment_provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_payment_provider');
        Schema::dropIfExists('check_settings');
        Schema::dropIfExists('payment_providers');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('payment_terms');
    }
};
