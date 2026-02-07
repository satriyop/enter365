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
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->char('currency_code', 3)->nullable()->after('credit');
            $table->bigInteger('amount_currency')->nullable()->after('currency_code');
            $table->decimal('exchange_rate', 18, 4)->nullable()->after('amount_currency');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'amount_currency', 'exchange_rate']);
        });
    }
};
