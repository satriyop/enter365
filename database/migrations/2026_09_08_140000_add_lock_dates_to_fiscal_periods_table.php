<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->date('lock_sales_until')->nullable();
            $table->date('lock_purchases_until')->nullable();
            $table->date('lock_tax_until')->nullable();
            $table->date('lock_everything_until')->nullable();
            $table->date('hard_lock_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_periods', function (Blueprint $table) {
            $table->dropColumn([
                'lock_sales_until',
                'lock_purchases_until',
                'lock_tax_until',
                'lock_everything_until',
                'hard_lock_until',
            ]);
        });
    }
};
