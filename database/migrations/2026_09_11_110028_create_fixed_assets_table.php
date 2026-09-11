<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 200);
            $table->foreignId('asset_model_id')->nullable()->constrained('asset_models')->nullOnDelete();
            $table->unsignedBigInteger('original_value');
            $table->unsignedBigInteger('salvage_value')->default(0);
            $table->date('acquisition_date');
            $table->string('method', 20)->default('linear');
            $table->unsignedSmallInteger('method_number');
            $table->string('method_period', 10)->default('month');
            $table->decimal('method_progress_factor', 8, 4)->nullable();
            $table->foreignId('asset_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('depreciation_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('accumulated_depreciation')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
