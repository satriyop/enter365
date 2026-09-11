<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytic_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analytic_budget_id')->constrained('analytic_budgets')->cascadeOnDelete();
            $table->foreignId('analytic_account_id')->constrained('analytic_accounts')->restrictOnDelete();
            $table->bigInteger('planned_amount');
            $table->timestamps();

            $table->unique(['analytic_budget_id', 'analytic_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytic_budget_lines');
    }
};
