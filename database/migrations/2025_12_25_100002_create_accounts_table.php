<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // e.g., "1-1001" for Kas
            $table->string('name'); // e.g., "Kas"
            $table->string('type'); // asset, liability, equity, revenue, expense
            $table->string('subtype')->nullable(); // current_asset, fixed_asset, etc.
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System accounts can't be deleted
            $table->bigInteger('opening_balance')->default(0); // In IDR (no decimals)
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
