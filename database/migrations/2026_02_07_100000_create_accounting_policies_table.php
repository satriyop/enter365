<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_policies', function (Blueprint $table) {
            $table->id();
            $table->string('inventory_method')->default('hybrid');
            $table->string('cogs_recognition')->default('on_invoice');
            $table->string('return_accounting')->default('full_journal');
            $table->string('manufacturing_costing')->default('project_based');
            $table->string('closing_strategy')->default('direct');
            $table->timestamps();
        });

        // Seed from current config values
        DB::table('accounting_policies')->insert([
            'inventory_method' => config('accounting.policies.inventory_method', 'hybrid'),
            'cogs_recognition' => config('accounting.policies.cogs_recognition', 'on_invoice'),
            'return_accounting' => config('accounting.policies.return_accounting', 'full_journal'),
            'manufacturing_costing' => config('accounting.policies.manufacturing_costing', 'project_based'),
            'closing_strategy' => config('accounting.policies.closing_strategy', 'direct'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_policies');
    }
};
