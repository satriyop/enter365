<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency', 3)->default('IDR')->after('amount');
            $table->decimal('exchange_rate', 18, 4)->default(1)->after('currency');
            $table->bigInteger('base_currency_amount')->nullable()->after('exchange_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'base_currency_amount']);
        });
    }
};
