<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytic_accounts', function (Blueprint $table) {
            $table->foreignId('analytic_plan_id')->nullable()->after('name')->constrained('analytic_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('analytic_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analytic_plan_id');
        });
    }
};
